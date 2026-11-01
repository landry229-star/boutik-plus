<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TablePdfExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class UtilisateurController extends Controller
{
    // Reserve au patron : gestion de son equipe (ajout / suppression d'employes)
    public function index(Request $request, TablePdfExporter $tablePdfExporter): View|Response
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'statut' => 'nullable|in:actif,archive,tous',
            'role' => 'nullable|in:patron,employe,tous',
            'date_debut' => 'nullable|date_format:Y-m-d',
            'date_fin' => 'nullable|date_format:Y-m-d',
        ]);

        if (! empty($filters['date_debut']) && ! empty($filters['date_fin']) && $filters['date_debut'] > $filters['date_fin']) {
            throw ValidationException::withMessages([
                'date_debut' => 'La date de début doit être antérieure ou égale à la date de fin.',
            ]);
        }

        $query = User::query();

        if (! $request->boolean('pdf')) {
            $query->withExists([
                'factures',
                'facturesAnnulees',
                'achats',
                'mouvementsStock',
                'mouvementsCaisse',
            ]);
        }

        if (! empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (($filters['statut'] ?? 'actif') !== 'tous') {
            $query->where('actif', ($filters['statut'] ?? 'actif') === 'actif');
        }

        if (($filters['role'] ?? 'tous') !== 'tous') {
            $query->where('role', $filters['role']);
        }

        if (! empty($filters['date_debut'])) {
            $query->whereDate('created_at', '>=', $filters['date_debut']);
        }

        if (! empty($filters['date_fin'])) {
            $query->whereDate('created_at', '<=', $filters['date_fin']);
        }

        $query->orderBy('name');

        if ($request->boolean('pdf')) {
            $tablePdfExporter->assertQueryWithinLimit($query);
            $utilisateurs = $query->get();
            $criteria = array_filter([
                isset($filters['q']) ? 'Recherche : '.$filters['q'] : null,
                isset($filters['statut']) ? 'Accès : '.$filters['statut'] : 'Accès : actif',
                isset($filters['role']) ? 'Rôle : '.$filters['role'] : 'Rôle : tous',
                isset($filters['date_debut']) ? 'Créé du : '.$filters['date_debut'] : null,
                isset($filters['date_fin']) ? 'Créé au : '.$filters['date_fin'] : null,
            ]);

            return $tablePdfExporter->download('Liste de l’équipe', 'equipe', [[
                'title' => 'Comptes ('.$utilisateurs->count().')',
                'headers' => ['Nom', 'E-mail', 'Rôle', 'Statut'],
                'rows' => $utilisateurs->map(fn (User $utilisateur): array => [
                    $utilisateur->name,
                    $utilisateur->email,
                    $utilisateur->estPatron() ? 'Patron' : 'Employé',
                    $utilisateur->actif ? 'Actif' : 'Désactivé',
                ])->all(),
            ]], array_values($criteria));
        }

        $utilisateurs = $query->paginate(25)->withQueryString();

        return view('utilisateurs.index', compact('utilisateurs', 'filters'));
    }

    public function create()
    {
        return view('utilisateurs.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:12',
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'employe',
        ]);

        return redirect()->route('utilisateurs.index')->with('success', 'Utilisateur ajoute avec succes.');
    }

    public function edit(User $utilisateur): View
    {
        abort_if($utilisateur->estPatron(), 403);

        return view('utilisateurs.edit', compact('utilisateur'));
    }

    public function update(Request $request, User $utilisateur): RedirectResponse
    {
        abort_if($utilisateur->estPatron(), 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($utilisateur->id)],
        ]);

        $utilisateur->update($data);

        return redirect()->route('utilisateurs.index')->with('success', 'Compte employé mis à jour.');
    }

    public function destroy(User $utilisateur)
    {
        if ($utilisateur->id === auth()->id() || $utilisateur->estPatron()) {
            return back()->withErrors(['utilisateur' => 'Un compte patron ne peut pas être désactivé depuis cette page.']);
        }

        $utilisateur->update(['actif' => false]);

        return redirect()->route('utilisateurs.index')->with('success', 'Compte désactivé. Son historique est conservé.');
    }

    public function statut(Request $request, User $utilisateur)
    {
        if ($utilisateur->estPatron()) {
            return back()->withErrors(['utilisateur' => 'Un compte patron ne peut pas être désactivé depuis cette page.']);
        }

        if ($utilisateur->id === $request->user()->id && ! $request->boolean('actif')) {
            return back()->withErrors(['utilisateur' => 'Vous ne pouvez pas désactiver votre propre compte.']);
        }

        $data = $request->validate([
            'actif' => 'required|boolean',
        ]);

        $utilisateur->update(['actif' => $data['actif']]);

        return redirect()->route('utilisateurs.index')->with('success', $data['actif'] ? 'Compte réactivé.' : 'Compte désactivé.');
    }

    public function supprimerDefinitivement(User $utilisateur): RedirectResponse
    {
        DB::transaction(function () use ($utilisateur) {
            $utilisateur = User::query()->lockForUpdate()->findOrFail($utilisateur->id);

            if ($utilisateur->actif || $utilisateur->estPatron()) {
                throw ValidationException::withMessages([
                    'utilisateur' => 'Seul un compte employé désactivé peut être supprimé définitivement.',
                ]);
            }

            $aUnHistorique = DB::table('factures')->where('user_id', $utilisateur->id)->exists()
                || DB::table('factures')->where('annulee_par_user_id', $utilisateur->id)->exists()
                || DB::table('achats')->where('user_id', $utilisateur->id)->exists()
                || DB::table('mouvements_stock')->where('user_id', $utilisateur->id)->exists()
                || DB::table('mouvements_caisse')->where('user_id', $utilisateur->id)->exists();

            if ($aUnHistorique) {
                throw ValidationException::withMessages([
                    'utilisateur' => 'Ce compte possède des opérations liées et ne peut pas être supprimé afin de préserver l’historique.',
                ]);
            }

            DB::table('sessions')->where('user_id', $utilisateur->id)->delete();
            DB::table('password_reset_tokens')->where('email', $utilisateur->email)->delete();
            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $utilisateur->id)
                ->delete();
            $utilisateur->delete();
        }, 3);

        return redirect()->route('utilisateurs.index', ['statut' => 'archive'])->with('success', 'Compte supprimé définitivement.');
    }
}

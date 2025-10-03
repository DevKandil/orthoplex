<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.read');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view their own profile or if they have permission
        return $user->id === $model->id || $user->can('users.read');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own profile (limited fields) or with permission
        if ($user->id === $model->id) {
            return true; // Own profile - but controller should limit fields
        }

        return $user->can('users.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Cannot delete yourself
        if ($user->id === $model->id) {
            return false;
        }

        // Cannot delete owners unless you're a higher-level owner
        if ($model->hasRole('owner') && !$user->hasRole('owner')) {
            return false;
        }

        return $user->can('users.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->can('users.delete'); // Same permission as delete
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Only owners can force delete
        return $user->hasRole('owner') && $user->can('users.delete');
    }

    /**
     * Determine whether the user can invite other users.
     */
    public function invite(User $user): bool
    {
        return $user->can('users.invite');
    }

    /**
     * Determine whether the user can view analytics.
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->can('analytics.read');
    }

    /**
     * Determine whether the user can manage roles for other users.
     */
    public function manageRoles(User $user, User $model): bool
    {
        // Cannot change your own roles
        if ($user->id === $model->id) {
            return false;
        }

        // Only owners can assign owner role
        if ($model->hasRole('owner') && !$user->hasRole('owner')) {
            return false;
        }

        return $user->can('users.update') && $user->hasRole(['owner', 'admin']);
    }

    /**
     * Determine whether the user can export user data (GDPR).
     */
    public function export(User $user, User $model): bool
    {
        // Users can export their own data or with permission
        return $user->id === $model->id || $user->can('users.read');
    }

    /**
     * Determine whether the user can request data deletion (GDPR).
     */
    public function requestDeletion(User $user, User $model): bool
    {
        // Users can request deletion of their own data
        return $user->id === $model->id;
    }
}
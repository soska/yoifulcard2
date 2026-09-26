<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\MembershipRole;
use App\Enums\ProgramType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user, together with their
     * organization, owner membership, and default "Gift Card" program.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $name = self::organizationName($user->name);

            $organization = Organization::create([
                'name' => $name,
                'slug' => Organization::uniqueSlug($name),
            ]);

            $organization->memberships()->create([
                'user_id' => $user->id,
                'role' => MembershipRole::Owner,
            ]);

            $organization->programs()->create([
                'name' => __('Gift Card'),
                'type' => ProgramType::Prepaid,
            ]);

            return $user;
        });
    }

    /**
     * Name the organization after the person, as the original app did, in
     * the current language.
     */
    public static function organizationName(string $personName): string
    {
        return __(':name\'s Business', ['name' => Str::limit(trim($personName), 240, '')]);
    }
}

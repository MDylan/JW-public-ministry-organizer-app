<?php

namespace Database\Factories;

use App\Models\AdminNewsletter;
use App\Models\AdminNewsletterRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdminNewsletterReadFactory extends Factory
{
    protected $model = AdminNewsletterRead::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'admin_newsletter_id' => AdminNewsletter::factory(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    public function forNewsletter(AdminNewsletter $newsletter): static
    {
        return $this->state(['admin_newsletter_id' => $newsletter->id]);
    }
}

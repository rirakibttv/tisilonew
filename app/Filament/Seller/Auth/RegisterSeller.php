<?php

namespace App\Filament\Seller\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VendorMemberRole;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use Filament\Auth\Pages\Register;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use SensitiveParameter;

class RegisterSeller extends Register
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent()->label('আপনার নাম'),
            TextInput::make('store_name')
                ->label('শপের নাম')
                ->required()
                ->maxLength(255),
            TextInput::make('phone')
                ->label('মোবাইল নম্বর')
                ->tel()
                ->required()
                ->regex('/^(?:\+?88)?01[3-9]\d{8}$/')
                ->maxLength(32),
            $this->getEmailFormComponent()->label('ইমেইল'),
            $this->getPasswordFormComponent()->label('পাসওয়ার্ড'),
            $this->getPasswordConfirmationFormComponent()->label('পাসওয়ার্ড নিশ্চিত করুন'),
        ]);
    }

    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => UserRole::VendorOwner,
            'status' => UserStatus::Active,
        ]);

        $vendor = Vendor::query()->create([
            'owner_id' => $user->getKey(),
            'name' => $data['store_name'],
            'slug' => $this->uniqueStoreSlug($data['store_name']),
            'email' => $data['email'],
            'phone' => $data['phone'],
            'status' => VendorStatus::Pending,
        ]);

        $vendor->members()->attach($user->getKey(), [
            'role' => VendorMemberRole::Owner->value,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Seller Registration';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'নতুন সেলার রেজিস্ট্রেশন';
    }

    protected function getFormActions(): array
    {
        return [
            $this->getRegisterFormAction()->label('সেলার অ্যাকাউন্ট তৈরি করুন'),
        ];
    }

    private function uniqueStoreSlug(string $storeName): string
    {
        $base = Str::slug($storeName) ?: 'seller-store';
        $slug = $base;
        $suffix = 2;

        while (Vendor::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}

<?php

namespace App\Filament\Pages;

use App\Enums\AdminNavigationGroup;
use App\Models\OperationalRecord;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

class OperationsModule extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'operations';

    protected string $view = 'filament.pages.operations-module';

    public string $module = '';

    public string $section = '';

    public ?int $editingId = null;

    public string $recordTitle = '';

    public ?string $reference = null;

    public string $status = 'active';

    public ?string $amount = null;

    public ?string $contact = null;

    public ?string $scheduledAt = null;

    public ?string $notes = null;

    public function mount(): void
    {
        $this->module = request()->string('module')->toString();
        $this->section = request()->string('section')->toString();

        abort_unless($this->configuration(), 404);
        $this->status = $this->configuration()['statuses'][0];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->configuration()['label'];
    }

    public function getSubheading(): ?string
    {
        return $this->configuration()['description'];
    }

    public function getModuleGroup(): AdminNavigationGroup
    {
        return AdminNavigationGroup::fromSlug($this->module);
    }

    /** @return array<string, mixed>|null */
    public function configuration(): ?array
    {
        return static::definitions()[$this->module][$this->section] ?? null;
    }

    /** @return Collection<int, OperationalRecord> */
    public function records(): Collection
    {
        return OperationalRecord::query()
            ->where('module', $this->module)
            ->where('section', $this->section)
            ->latest()
            ->limit(100)
            ->get();
    }

    /** @return array<string, int|float> */
    public function metrics(): array
    {
        $query = OperationalRecord::query()
            ->where('module', $this->module)
            ->where('section', $this->section);

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->whereIn('status', ['active', 'approved', 'published', 'completed', 'paid', 'processed'])->count(),
            'pending' => (clone $query)->whereIn('status', ['pending', 'draft', 'in_review', 'open'])->count(),
            'amount' => (float) (clone $query)->sum('amount'),
        ];
    }

    public function save(): void
    {
        $config = $this->configuration();
        $validated = $this->validate([
            'recordTitle' => ['required', 'string', 'max:255'],
            'reference' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('operational_records', 'reference')
                    ->where(fn ($query) => $query->where('module', $this->module)->where('section', $this->section))
                    ->ignore($this->editingId),
            ],
            'status' => ['required', Rule::in($config['statuses'])],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'contact' => ['nullable', 'string', 'max:255'],
            'scheduledAt' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'recordTitle.required' => 'নাম বা শিরোনাম লিখুন।',
            'reference.unique' => 'এই রেফারেন্সটি ইতোমধ্যে ব্যবহার করা হয়েছে।',
        ]);

        OperationalRecord::query()->updateOrCreate(
            ['id' => $this->editingId],
            [
                'module' => $this->module,
                'section' => $this->section,
                'title' => $validated['recordTitle'],
                'reference' => filled($validated['reference']) ? $validated['reference'] : null,
                'status' => $validated['status'],
                'amount' => filled($validated['amount']) ? $validated['amount'] : null,
                'contact' => filled($validated['contact']) ? $validated['contact'] : null,
                'scheduled_at' => filled($validated['scheduledAt']) ? $validated['scheduledAt'] : null,
                'notes' => filled($validated['notes']) ? $validated['notes'] : null,
                'created_by' => auth()->id(),
            ],
        );

        $this->resetForm();
        Notification::make()->title('তথ্য সফলভাবে সংরক্ষণ হয়েছে')->success()->send();
    }

    public function edit(int $id): void
    {
        $record = $this->record($id);
        $this->editingId = $record->id;
        $this->recordTitle = $record->title;
        $this->reference = $record->reference;
        $this->status = $record->status;
        $this->amount = $record->amount;
        $this->contact = $record->contact;
        $this->scheduledAt = $record->scheduled_at?->format('Y-m-d\TH:i');
        $this->notes = $record->notes;
    }

    public function delete(int $id): void
    {
        $this->record($id)->delete();
        if ($this->editingId === $id) {
            $this->resetForm();
        }

        Notification::make()->title('রেকর্ডটি মুছে ফেলা হয়েছে')->success()->send();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'recordTitle', 'reference', 'amount', 'contact', 'scheduledAt', 'notes']);
        $this->status = $this->configuration()['statuses'][0];
        $this->resetValidation();
    }

    private function record(int $id): OperationalRecord
    {
        return OperationalRecord::query()
            ->where('module', $this->module)
            ->where('section', $this->section)
            ->findOrFail($id);
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    public static function definitions(): array
    {
        $standard = ['active', 'inactive', 'draft'];
        $workflow = ['pending', 'approved', 'processed', 'rejected'];

        return [
            'pos-system' => [
                'new-sale' => ['label' => 'New POS Sale', 'description' => 'দ্রুত কাউন্টার বিক্রয়, পেমেন্ট ও ইনভয়েস রেকর্ড করুন।', 'statuses' => ['completed', 'held', 'cancelled']],
                'sales-history' => ['label' => 'POS Sales History', 'description' => 'কাউন্টার বিক্রয়ের পূর্ণ ইতিহাস ও মোট বিক্রয় দেখুন।', 'statuses' => ['completed', 'refunded', 'cancelled']],
            ],
            'fraud-checker-api' => [
                'check' => ['label' => 'Fraud Check', 'description' => 'ফোন বা অর্ডার রেফারেন্স দিয়ে ঝুঁকি যাচাই সংরক্ষণ করুন।', 'statuses' => ['pending', 'safe', 'risky', 'blocked']],
                'history' => ['label' => 'Fraud Check History', 'description' => 'আগের যাচাই এবং সিদ্ধান্তসমূহ পর্যালোচনা করুন।', 'statuses' => ['safe', 'risky', 'blocked', 'pending']],
            ],
            'shipping' => [
                'charges' => ['label' => 'Shipping Charge', 'description' => 'ডেলিভারি জোন, চার্জ এবং সময়সীমা পরিচালনা করুন।', 'statuses' => $standard],
            ],
            'offer-panel' => [
                'banners' => ['label' => 'Banner & Sliders', 'description' => 'স্টোরফ্রন্ট ব্যানার ও স্লাইডার ক্যাম্পেইন পরিচালনা করুন।', 'statuses' => ['active', 'scheduled', 'expired', 'draft']],
                'popup' => ['label' => 'Popup Offer', 'description' => 'সময়ভিত্তিক পপআপ অফার প্রকাশ ও নিয়ন্ত্রণ করুন।', 'statuses' => ['active', 'scheduled', 'expired', 'draft']],
            ],
            'vendors' => [
                'verifications' => ['label' => 'Vendor Verifications', 'description' => 'ভেন্ডরের কাগজপত্র যাচাই ও অনুমোদনের সিদ্ধান্ত রাখুন।', 'statuses' => $workflow],
                'withdrawals' => ['label' => 'Vendor Withdrawals', 'description' => 'ভেন্ডর পেআউট অনুরোধ এবং নিষ্পত্তি পরিচালনা করুন।', 'statuses' => ['pending', 'approved', 'paid', 'rejected']],
            ],
            'refunds' => [
                'all' => ['label' => 'All Refunds', 'description' => 'সকল রিফান্ড অনুরোধ ও অর্থের হিসাব পরিচালনা করুন।', 'statuses' => ['pending', 'approved', 'processed', 'rejected']],
                'pending' => ['label' => 'Pending Refunds', 'description' => 'অপেক্ষমাণ রিফান্ড যাচাই করুন।', 'statuses' => $workflow],
                'approved' => ['label' => 'Approved Refunds', 'description' => 'অনুমোদিত রিফান্ড নিষ্পত্তি করুন।', 'statuses' => ['approved', 'processed', 'rejected']],
                'processed' => ['label' => 'Processed Refunds', 'description' => 'সম্পন্ন রিফান্ডের অডিট ট্রেইল দেখুন।', 'statuses' => ['processed', 'approved']],
            ],
            'coupons' => [
                'all' => ['label' => 'All Coupons', 'description' => 'কুপন কোড, ছাড় এবং মেয়াদ পরিচালনা করুন।', 'statuses' => ['active', 'scheduled', 'expired', 'inactive']],
                'create' => ['label' => 'Add New Coupon', 'description' => 'নতুন প্রোমো কোড ও ছাড়ের নিয়ম তৈরি করুন।', 'statuses' => ['active', 'scheduled', 'draft']],
            ],
            'blog' => [
                'all' => ['label' => 'All Blogs', 'description' => 'ব্লগ পোস্ট ও প্রকাশনার অবস্থা পরিচালনা করুন।', 'statuses' => ['published', 'draft', 'scheduled', 'archived']],
                'create' => ['label' => 'Add New Blog', 'description' => 'SEO-বান্ধব নতুন আর্টিকেল তৈরি করুন।', 'statuses' => ['draft', 'published', 'scheduled']],
            ],
            'accounts' => [
                'purchases' => ['label' => 'Purchases', 'description' => 'ক্রয়, বিল ও সরবরাহ গ্রহণের হিসাব রাখুন।', 'statuses' => ['pending', 'received', 'paid', 'cancelled']],
                'suppliers' => ['label' => 'Suppliers', 'description' => 'সরবরাহকারী যোগাযোগ ও স্ট্যাটাস পরিচালনা করুন।', 'statuses' => $standard],
                'funds' => ['label' => 'Fund / Accounts', 'description' => 'ক্যাশ, ব্যাংক ও ফান্ড ব্যালেন্স রেকর্ড করুন।', 'statuses' => ['active', 'reconciled', 'closed']],
                'expenses' => ['label' => 'Expenses', 'description' => 'ব্যয়, ভাউচার ও অনুমোদন রেকর্ড করুন।', 'statuses' => ['pending', 'approved', 'paid', 'rejected']],
            ],
            'crm-hr' => [
                'employees' => ['label' => 'Employees', 'description' => 'কর্মী প্রোফাইল ও চাকরির অবস্থা পরিচালনা করুন।', 'statuses' => $standard],
                'attendance' => ['label' => 'Attendance', 'description' => 'উপস্থিতি, অনুপস্থিতি ও কর্মঘণ্টা রাখুন।', 'statuses' => ['present', 'absent', 'late', 'leave']],
                'leaves' => ['label' => 'Leaves', 'description' => 'ছুটির আবেদন ও অনুমোদন পরিচালনা করুন।', 'statuses' => $workflow],
                'salaries' => ['label' => 'Salaries', 'description' => 'মাসিক বেতন প্রস্তুতি ও স্ট্যাটাস রাখুন।', 'statuses' => ['draft', 'approved', 'paid']],
                'bonuses' => ['label' => 'Bonuses', 'description' => 'বোনাস ও ইনসেনটিভ হিসাব রাখুন।', 'statuses' => ['draft', 'approved', 'paid']],
                'salary-payments' => ['label' => 'Salary Payments', 'description' => 'বেতন পরিশোধ ও রেফারেন্স সংরক্ষণ করুন।', 'statuses' => ['pending', 'paid', 'failed']],
            ],
            'reviews' => [
                'pending' => ['label' => 'Pending Reviews', 'description' => 'ক্রেতার রিভিউ যাচাই ও মডারেট করুন।', 'statuses' => $workflow],
                'all' => ['label' => 'All Reviews', 'description' => 'সকল রেটিং ও রিভিউ পরিচালনা করুন।', 'statuses' => ['approved', 'pending', 'rejected']],
                'create' => ['label' => 'Create Review', 'description' => 'প্রয়োজনে যাচাইকৃত রিভিউ রেকর্ড যোগ করুন।', 'statuses' => ['pending', 'approved']],
            ],
            'complaints' => [
                'all' => ['label' => 'All Complaints', 'description' => 'ক্রেতা/ভেন্ডর অভিযোগ, অগ্রাধিকার ও সমাধান ট্র্যাক করুন।', 'statuses' => ['open', 'in_review', 'resolved', 'closed']],
            ],
            'marketing' => [
                'sms' => ['label' => 'Send Custom SMS', 'description' => 'SMS ক্যাম্পেইন প্রস্তুত ও পাঠানোর রেকর্ড রাখুন।', 'statuses' => ['draft', 'scheduled', 'sent', 'failed']],
                'messages' => ['label' => 'Contact Messages', 'description' => 'ওয়েবসাইটের ইনকোয়ারি ও উত্তর দেওয়ার অবস্থা পরিচালনা করুন।', 'statuses' => ['open', 'in_review', 'resolved', 'spam']],
                'newsletter' => ['label' => 'Newsletter Subscribers', 'description' => 'ইমেইল সাবস্ক্রাইবার ও ক্যাম্পেইন সম্মতি পরিচালনা করুন।', 'statuses' => ['active', 'unsubscribed', 'bounced']],
            ],
            'live-ads-result' => [
                'overview' => ['label' => 'Ads Overview', 'description' => 'সকল বিজ্ঞাপন প্ল্যাটফর্মের ব্যয় ও ফলাফল রেকর্ড করুন।', 'statuses' => ['active', 'paused', 'completed']],
                'facebook' => ['label' => 'Facebook Ads', 'description' => 'Facebook ক্যাম্পেইনের ফলাফল ও ব্যয় পর্যবেক্ষণ করুন।', 'statuses' => ['active', 'paused', 'completed']],
                'google' => ['label' => 'Google Ads', 'description' => 'Google Ads ক্যাম্পেইনের ফলাফল সংরক্ষণ করুন।', 'statuses' => ['active', 'paused', 'completed']],
                'tiktok' => ['label' => 'TikTok Ads', 'description' => 'TikTok ক্যাম্পেইনের ফলাফল সংরক্ষণ করুন।', 'statuses' => ['active', 'paused', 'completed']],
            ],
            'pages' => [
                'all' => ['label' => 'All Pages', 'description' => 'স্টোরফ্রন্টের কনটেন্ট পেজ ও প্রকাশনা পরিচালনা করুন।', 'statuses' => ['published', 'draft', 'archived']],
                'create' => ['label' => 'Create Page', 'description' => 'নতুন নীতিমালা, সহায়তা বা তথ্য পেজ তৈরি করুন।', 'statuses' => ['draft', 'published']],
            ],
        ];
    }
}

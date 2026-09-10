<?php

namespace Database\Seeders;

use App\Models\LandingPage;
use App\Models\Product;
use Illuminate\Database\Seeder;

class LandingPageTemplateSeeder extends Seeder
{
    /**
     * Give a new installation an editable, unpublished Facebook campaign.
     * Existing landing pages are never changed by this seeder.
     */
    public function run(): void
    {
        if (LandingPage::query()->exists()) {
            return;
        }

        $product = Product::query()
            ->where('status', 'published')
            ->orderByDesc('featured')
            ->orderBy('sort_order')
            ->first();

        if (! $product) {
            return;
        }

        $landingPage = LandingPage::query()->create([
            'name' => 'Facebook Special Offer',
            'slug' => 'facebook-special-offer',
            'status' => 'draft',
            'hero_badge' => 'আজকের বিশেষ অফার',
            'headline' => $product->name.' — বিশেষ মূল্যে আজই অর্ডার করুন',
            'subheadline' => 'দ্রুত ডেলিভারি, নিরাপদ পেমেন্ট এবং সহজ রিটার্ন সুবিধাসহ সরাসরি অর্ডার করুন।',
            'cta_text' => 'এখনই অর্ডার করুন',
            'theme_color' => '#f97316',
            'offer_title' => 'কেন এই অফারটি আপনার জন্য',
            'offer_body' => '<p>আপনার ক্যাম্পেইনের মূল অফার, ব্যবহারবিধি এবং ক্রেতার উপকারিতা এখানে লিখুন। প্রকাশের আগে ছবি, মূল্য ও সব তথ্য যাচাই করুন।</p>',
            'trust_title' => 'গ্রাহকের আস্থা, আমাদের অঙ্গীকার',
            'benefits' => [
                ['title' => 'মান যাচাইকৃত পণ্য', 'description' => 'প্রতিটি অর্ডার পাঠানোর আগে যত্নসহকারে যাচাই করা হয়।'],
                ['title' => 'সারাদেশে ডেলিভারি', 'description' => 'নির্ভরযোগ্য ডেলিভারি সেবায় দ্রুত পণ্য পৌঁছে দেওয়া হয়।'],
                ['title' => 'সহজ সহায়তা', 'description' => 'অর্ডার ও পণ্য সম্পর্কে সহায়তার জন্য আমাদের টিম প্রস্তুত।'],
            ],
            'reviews' => [],
            'faqs' => [
                ['question' => 'কীভাবে অর্ডার করব?', 'answer' => 'পরিমাণ নির্বাচন করে “এখনই অর্ডার করুন” বাটনে চাপুন এবং প্রয়োজনীয় তথ্য দিন।'],
                ['question' => 'কত দিনে ডেলিভারি পাব?', 'answer' => 'এলাকাভেদে সাধারণত ২–৫ কর্মদিবসের মধ্যে ডেলিভারি সম্পন্ন হয়।'],
                ['question' => 'ক্যাশ অন ডেলিভারি আছে?', 'answer' => 'চেকআউটে উপলভ্য পেমেন্ট পদ্ধতি থেকে ক্যাশ অন ডেলিভারি নির্বাচন করতে পারবেন।'],
            ],
        ]);

        $landingPage->products()->attach($product->getKey(), ['sort_order' => 0]);
    }
}

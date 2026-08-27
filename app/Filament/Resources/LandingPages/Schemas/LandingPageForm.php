<?php

namespace App\Filament\Resources\LandingPages\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class LandingPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Campaign Basics')
                ->description('Create a focused URL for a Facebook or social media advertisement.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Internal Campaign Name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                    TextInput::make('slug')
                        ->prefix('/offer/')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Select::make('status')
                        ->options(['draft' => 'Draft', 'published' => 'Published'])
                        ->default('draft')
                        ->required(),
                    DateTimePicker::make('published_at')
                        ->label('Publish At')
                        ->helperText('Leave blank to publish immediately when status is Published.'),
                    Select::make('products')
                        ->relationship('products', 'name', modifyQueryUsing: fn ($query) => $query->where('status', 'published'))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),
                ]),

            Section::make('Hero & Primary CTA')
                ->columns(2)
                ->schema([
                    TextInput::make('hero_badge')->placeholder('আজকের বিশেষ অফার')->maxLength(255),
                    ColorPicker::make('theme_color')->default('#f97316')->required(),
                    TextInput::make('headline')->required()->maxLength(255)->columnSpanFull(),
                    Textarea::make('subheadline')->rows(3)->maxLength(600)->columnSpanFull(),
                    TextInput::make('cta_text')->default('এখনই অর্ডার করুন')->required()->maxLength(80),
                    DateTimePicker::make('countdown_ends_at')->label('Offer Countdown Ends'),
                    FileUpload::make('hero_image')
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('landing-pages/heroes')
                        ->visibility('public'),
                    FileUpload::make('og_image')
                        ->label('Facebook Share Image')
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('landing-pages/social')
                        ->visibility('public'),
                ]),

            Section::make('Offer Story')
                ->columns(2)
                ->schema([
                    TextInput::make('offer_title')->maxLength(255)->columnSpanFull(),
                    RichEditor::make('offer_body')->columnSpanFull(),
                    TextInput::make('trust_title')
                        ->placeholder('হাজারো সন্তুষ্ট গ্রাহকের পছন্দ')
                        ->maxLength(255),
                    TextInput::make('video_url')
                        ->label('YouTube Video URL')
                        ->url()
                        ->maxLength(255),
                ]),

            Section::make('Benefits')
                ->description('Short, outcome-focused reasons to buy.')
                ->schema([
                    Repeater::make('benefits')
                        ->schema([
                            TextInput::make('title')->required()->maxLength(120),
                            Textarea::make('description')->rows(2)->maxLength(300),
                        ])
                        ->columns(2)
                        ->defaultItems(3)
                        ->reorderable()
                        ->collapsible(),
                ]),

            Section::make('Social Proof')
                ->columns(1)
                ->schema([
                    FileUpload::make('gallery_images')
                        ->label('Product & Customer Gallery')
                        ->multiple()
                        ->reorderable()
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('landing-pages/gallery')
                        ->visibility('public'),
                    Repeater::make('reviews')
                        ->schema([
                            TextInput::make('name')->required()->maxLength(100),
                            Select::make('rating')->options([5 => '5 Stars', 4 => '4 Stars', 3 => '3 Stars'])->default(5)->required(),
                            Textarea::make('quote')->required()->rows(3)->maxLength(500)->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->reorderable()
                        ->collapsible(),
                ]),

            Section::make('Frequently Asked Questions')
                ->schema([
                    Repeater::make('faqs')
                        ->schema([
                            TextInput::make('question')->required()->maxLength(255),
                            Textarea::make('answer')->required()->rows(3)->maxLength(800),
                        ])
                        ->defaultItems(0)
                        ->reorderable()
                        ->collapsible(),
                ]),

            Section::make('Facebook Ads & SEO')
                ->columns(2)
                ->schema([
                    TextInput::make('facebook_pixel_id')
                        ->label('Meta Pixel ID')
                        ->helperText('Configuration placeholder only. External Meta tracking stays disabled until data-sharing is explicitly authorised.')
                        ->regex('/^[0-9]{5,32}$/')
                        ->maxLength(32),
                    TextInput::make('meta_title')->maxLength(255),
                    Textarea::make('meta_description')->rows(3)->maxLength(500)->columnSpanFull(),
                ]),
        ]);
    }
}

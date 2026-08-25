<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum AdminNavigationGroup implements HasIcon, HasLabel
{
    case PosSystem;
    case FraudCheckerApi;
    case ProductsInfo;
    case OrderPanel;
    case Shipping;
    case OfferPanel;
    case Vendors;
    case Refunds;
    case Coupons;
    case Blog;
    case Accounts;
    case CrmHr;
    case Reviews;
    case LandingPage;
    case Complaints;
    case Marketing;
    case User;
    case SeoOverview;
    case LiveAdsResult;
    case ApiIntegration;
    case Pages;
    case GeneralSettings;

    public function getLabel(): string
    {
        return match ($this) {
            self::PosSystem => 'POS System',
            self::FraudCheckerApi => 'Fraud Checker API',
            self::ProductsInfo => 'Products Info',
            self::OrderPanel => 'Order Panel',
            self::Shipping => 'Shipping',
            self::OfferPanel => 'Offer Panel',
            self::Vendors => 'Vendors',
            self::Refunds => 'Refunds',
            self::Coupons => 'Coupons',
            self::Blog => 'Blog',
            self::Accounts => 'Accounts',
            self::CrmHr => 'CRM / HR',
            self::Reviews => 'Reviews',
            self::LandingPage => 'Landing Page',
            self::Complaints => 'Complaints',
            self::Marketing => 'Marketing',
            self::User => 'User',
            self::SeoOverview => 'SEO Overview',
            self::LiveAdsResult => 'Live Ads Result',
            self::ApiIntegration => 'API Integration',
            self::Pages => 'Pages',
            self::GeneralSettings => 'General Settings',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::PosSystem => Heroicon::OutlinedCalculator,
            self::FraudCheckerApi => Heroicon::OutlinedShieldCheck,
            self::ProductsInfo => Heroicon::OutlinedShoppingBag,
            self::OrderPanel => Heroicon::OutlinedClipboardDocumentList,
            self::Shipping => Heroicon::OutlinedTruck,
            self::OfferPanel => Heroicon::OutlinedMegaphone,
            self::Vendors => Heroicon::OutlinedBuildingStorefront,
            self::Refunds => Heroicon::OutlinedArrowUturnLeft,
            self::Coupons => Heroicon::OutlinedTicket,
            self::Blog => Heroicon::OutlinedBookOpen,
            self::Accounts => Heroicon::OutlinedBanknotes,
            self::CrmHr => Heroicon::OutlinedUsers,
            self::Reviews => Heroicon::OutlinedStar,
            self::LandingPage => Heroicon::OutlinedWindow,
            self::Complaints => Heroicon::OutlinedChatBubbleLeftRight,
            self::Marketing => Heroicon::OutlinedPresentationChartLine,
            self::User => Heroicon::OutlinedUserGroup,
            self::SeoOverview => Heroicon::OutlinedMagnifyingGlass,
            self::LiveAdsResult => Heroicon::OutlinedChartBar,
            self::ApiIntegration => Heroicon::OutlinedPuzzlePiece,
            self::Pages => Heroicon::OutlinedDocumentText,
            self::GeneralSettings => Heroicon::OutlinedCog6Tooth,
        };
    }

    public function slug(): string
    {
        return str($this->getLabel())->slug()->toString();
    }

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $group) {
            if ($group->slug() === $slug) {
                return $group;
            }
        }

        return null;
    }
}

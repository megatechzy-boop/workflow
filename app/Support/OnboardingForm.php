<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Client onboarding questions (same as megatechzy.com/Client_Onboarding.html), minus passwords.
 * field: [key, label, type, options?]; types: text, textarea, url, email, tel, radio, checkbox.
 */
class OnboardingForm
{
    public static function sections(): array
    {
        return [
            'Business Profile & Operations' => [
                ['business_name', 'Business Name', 'text'],
                ['owner', 'Owner / Contact Person', 'text'],
                ['contact_number', 'Contact Number', 'tel'],
                ['location', 'Physical Location', 'text'],
                ['category', 'Industry / Category', 'text'],
                ['website', 'Website URL', 'text'],
                ['social_links', 'Social Media Accounts (links only, no passwords)', 'textarea'],
                ['stage', 'Business Stage', 'radio', ['Startup / New', 'Established & Growing', 'Expansion Phase']],
                ['operations', 'Business Operations (Timings, Areas, Availability)', 'textarea'],
                ['assets', 'Available Assets', 'checkbox', ['Logo / Brand Guidelines', 'Professional Photos/Videos', 'Brochures', 'Previous Ad Creatives']],
            ],
            'Project Scope & Business Goals' => [
                ['deliverables', 'What specific deliverables are you hiring Mega Techzy for?', 'textarea'],
                ['goals', 'Exact 6–12 month business goals (Revenue, Market Growth, etc.)', 'textarea'],
                ['priority', 'What is your biggest priority right now?', 'checkbox', ['Generate Leads', 'Increase Sales', 'Brand Awareness', 'Website Traffic', 'Social Media Growth', 'Improve Online Reputation', 'Other']],
                ['priority_other', 'Other Priority (specify)', 'text'],
                ['target_area', 'Target Service Area / Target Locations', 'text'],
            ],
            'Products, Pricing & Offers' => [
                ['offerings', 'Core Offerings (Main & Secondary Services)', 'textarea'],
                ['avg_price', 'Average Selling Price', 'text'],
                ['margins', 'Profit Margins (optional)', 'text'],
                ['promotions', 'Active Promotions (Discounts, Combos, Trials)', 'textarea'],
                ['payment_methods', 'Accepted Payment Methods', 'checkbox', ['EMI', 'Online Payment', 'Cash / COD', 'Advance Payment']],
            ],
            'Target Audience & Customer Journey' => [
                ['ideal_customer', 'Ideal Customer Profile (Age, Income, Profession, Location, B2B/B2C)', 'textarea'],
                ['pain_point', 'Primary Customer Pain Point / Need', 'textarea'],
                ['journey', 'Customer Journey (Discovery → Inquiry → Sale)', 'textarea'],
                ['sales_dynamics', 'Sales Dynamics (Decision Maker, Common Objections, FAQs)', 'textarea'],
            ],
            'Brand Positioning & Competitors' => [
                ['brand_identity', 'Desired Brand Identity (e.g., Premium, Affordable, Disruptive)', 'text'],
                ['content_prefs', 'Content Preferences (Language, Tone, Visual Style)', 'textarea'],
                ['competitors', 'Top Competitors & Your Unique Selling Proposition (USP)', 'textarea'],
                ['content_support', 'Content Support Required', 'checkbox', ['Social Media Posts', 'Reels', 'Blogs', 'Website Content', 'Ad Creatives', 'Videos', 'Photography', 'Other']],
                ['content_other', 'Other Content Needed (specify)', 'text'],
            ],
            'Marketing History & Ads' => [
                ['channels', 'Currently Active Marketing Channels', 'textarea'],
                ['budget', 'Monthly Marketing Budget (optional)', 'text'],
                ['history', 'Historical Data (Previous results, what worked/failed)', 'textarea'],
                ['best_offer', 'Historically Best Performing Product/Offer', 'text'],
            ],
            'Approval & Communication' => [
                ['poc_name', 'Primary Point of Contact: Name', 'text'],
                ['poc_designation', 'Designation', 'text'],
                ['poc_email', 'Contact Email', 'email'],
                ['poc_phone', 'Contact Phone', 'tel'],
                ['approver', 'Who approves content / campaigns?', 'text'],
                ['approval_time', 'Expected approval timeline', 'radio', ['Same day', '1-2 working days', '3-5 working days', 'Other']],
            ],
            'Access & Account Details' => [
                ['domain', 'Domain Name & Registrar', 'text'],
                ['hosting', 'Hosting Provider & Type', 'text'],
                ['platform', 'Website Platform', 'text'],
                ['account_manager', 'Who manages your accounts?', 'text'],
                ['access', 'Access we will need (check what you can provide)', 'checkbox', ['Domain / DNS Access', 'Hosting / cPanel Access', 'Website Admin Access', 'Google Analytics (GA4)', 'Google Search Console', 'Google Tag Manager', 'Google Ads Account', 'Meta Business Manager', 'Meta Ads Account', 'Facebook Page (Admin)', 'Instagram Account', 'YouTube Channel', 'LinkedIn Page', 'X (Twitter) / Threads', 'Google Business Profile', 'WhatsApp Business', 'Email / Mailchimp / CRM']],
                ['access_notes', 'Access Notes (accounts not created yet, who to contact). Please do not write passwords here; we will request access through Meta Business / Google.', 'textarea'],
            ],
        ];
    }

    /** Industry => its extra questions (section 09) */
    public static function industries(): array
    {
        return [
            'Real Estate' => [['re_property', 'Property Type & Location'], ['re_price', 'Price Range & Amenities'], ['re_rera', 'Possession Timeline & RERA Status']],
            'Healthcare' => [['hc_treatments', 'Treatment Types & Specialists'], ['hc_format', 'Consultation Format (Online/Offline)'], ['hc_appointments', 'Appointment Process']],
            'Education' => [['ed_courses', 'Course Details & Duration'], ['ed_fees', 'Fees & Target Students'], ['ed_placements', 'Certification & Placements']],
            'E-commerce' => [['ec_features', 'Key Features & Benefits'], ['ec_shipping', 'Shipping Logistics'], ['ec_returns', 'Return Policy']],
            'Local Services' => [['ls_areas', 'Specific Service Areas'], ['ls_pricing', 'Transparent Pricing Structure'], ['ls_booking', 'Exact Booking Process']],
            'IT & Software / Services' => [['it_services', 'Services / Products Offered'], ['it_stack', 'Tech Stack / Platforms'], ['it_clients', 'Client Type (SMB / Enterprise / Startup)'], ['it_pricing', 'Pricing Model (Project / Retainer / Subscription)'], ['it_demo', 'Demo / Trial Available?']],
            'Yoga & Wellness' => [['yw_classes', 'Classes / Programs (Online / Offline)'], ['yw_batches', 'Batch Timings & Duration'], ['yw_fees', 'Fees & Membership Plans'], ['yw_certs', 'Instructor Certifications'], ['yw_location', 'Studio Location / Online Platform']],
            'Coaching & Consulting' => [['cc_niche', 'Coaching Niche & Programs'], ['cc_format', 'Session Format (1:1 / Group / Online)'], ['cc_pricing', 'Packages & Pricing'], ['cc_clients', 'Target Clients & Transformation Promised'], ['cc_proof', 'Testimonials / Case Studies Available']],
            'Other' => [['ot_industry', 'Describe Your Industry'], ['ot_services', 'Key Services / Products'], ['ot_buying', 'How Customers Book / Buy']],
        ];
    }

    /** Validation rules for every field (all optional, sized) */
    public static function rules(): array
    {
        $rules = ['industry' => ['nullable', Rule::in(array_keys(self::industries()))], 'consent' => ['accepted']];
        foreach (self::sections() as $fields) {
            foreach ($fields as [$key, , $type]) {
                $rules[$key] = $type === 'checkbox' ? ['nullable', 'array'] : ['nullable', 'string', 'max:'.($type === 'textarea' ? 3000 : 255)];
                if ($type === 'checkbox') {
                    $rules[$key.'.*'] = ['string', 'max:100'];
                }
            }
        }
        foreach (self::industries() as $fields) {
            foreach ($fields as [$key]) {
                $rules[$key] = ['nullable', 'string', 'max:1000'];
            }
        }

        return $rules;
    }
}

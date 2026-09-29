<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

/**
 * The public landing page's content, stored as one JSON row (the demo's config_sections).
 *
 * @property array<string, mixed> $config
 */
#[Fillable(['config'])]
class LandingPageSetting extends Model
{
    /** Sections that can be reordered; the footer always comes last. */
    public const ORDERABLE = ['hero', 'features', 'screenshots', 'why_choose_us', 'about', 'team', 'testimonials', 'faq', 'newsletter', 'contact'];

    /** Lucide icons the page can render (see resources/js/lib/landing-icons.ts). */
    public const ICONS = ['users', 'dollar-sign', 'clock', 'user-plus', 'award', 'bar-chart-2', 'layout-dashboard', 'file-text', 'calendar-days', 'layers', 'shield', 'target', 'heart', 'lightbulb', 'star', 'rocket', 'briefcase', 'globe', 'zap', 'check-circle'];

    private const STAT = ['value', 'label'];

    private const CARD = ['icon', 'title', 'description'];

    /**
     * Each section's text fields, and its repeatable lists (field => item keys).
     */
    public const SCHEMA = [
        'hero' => ['announcement_text', 'title', 'subtitle', 'stats' => self::STAT],
        'features' => ['title', 'description', 'features_list' => self::CARD],
        'screenshots' => ['title', 'subtitle', 'screenshots_list' => self::CARD],
        'why_choose_us' => ['title', 'subtitle', 'reasons' => self::CARD, 'stats' => self::STAT],
        'about' => ['title', 'description', 'story_title', 'story_content', 'stats' => self::STAT, 'values' => self::CARD],
        'team' => ['title', 'subtitle', 'cta_title', 'cta_description', 'cta_button_text', 'members' => ['name', 'role', 'bio', 'email']],
        'testimonials' => ['title', 'subtitle', 'trust_title', 'testimonials' => ['name', 'role', 'company', 'content'], 'trust_stats' => self::STAT],
        'faq' => ['title', 'subtitle', 'cta_text', 'button_text', 'faqs' => ['question', 'answer']],
        'newsletter' => ['title', 'subtitle', 'privacy_text', 'benefits' => self::CARD],
        'contact' => ['title', 'subtitle', 'form_title', 'info_title', 'info_description', 'email', 'phone', 'address'],
        'footer' => ['description', 'newsletter_title', 'newsletter_subtitle'],
    ];

    protected function casts(): array
    {
        return ['config' => 'array'];
    }

    public static function defaultsPath(): string
    {
        return database_path('demo/landing-page.json');
    }

    /**
     * The shipped content (also what the seeder stores).
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return File::json(self::defaultsPath(), JSON_THROW_ON_ERROR);
    }

    /**
     * The saved content, or the shipped defaults before anything is saved.
     *
     * @return array<string, mixed>
     */
    public static function content(): array
    {
        return static::query()->first()->config ?? self::defaults();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function store(array $config): void
    {
        $row = static::query()->first() ?? new self;
        $row->fill(['config' => $config])->save();
    }

    /**
     * Validation rules for the whole content payload.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $sections = array_keys(self::SCHEMA);
        $rules = [
            'section_order' => ['required', 'array', 'size:'.count(self::ORDERABLE)],
            'section_order.*' => ['required', 'distinct', Rule::in(self::ORDERABLE)],
            'section_visibility' => ['required', 'array', 'required_array_keys:'.implode(',', $sections)],
            'section_visibility.*' => ['boolean'],
            'sections' => ['required', 'array'],
        ];

        foreach (self::SCHEMA as $section => $fields) {
            foreach ($fields as $field => $keys) {
                if (is_string($keys)) {
                    $rules["sections.$section.$keys"] = self::textRule($keys, 2000);

                    continue;
                }

                $rules["sections.$section.$field"] = ['present', 'array', 'max:30'];

                foreach ($keys as $key) {
                    $rules["sections.$section.$field.*.$key"] = match (true) {
                        $key === 'icon' && $section === 'newsletter' => ['required', 'string', 'max:16'],
                        $key === 'icon' => ['required', Rule::in(self::ICONS)],
                        default => self::textRule($key, 1000),
                    };
                }
            }
        }

        return $rules;
    }

    /**
     * @return list<string>
     */
    private static function textRule(string $key, int $max): array
    {
        return $key === 'email' ? ['nullable', 'email', 'max:255'] : ['nullable', 'string', 'max:'.$max];
    }

    /**
     * Keep only known fields (validation passes whole arrays through), with blanks as ''.
     *
     * @param  array<string, mixed>  $input  validated input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $sections = [];

        foreach (self::SCHEMA as $section => $fields) {
            foreach ($fields as $field => $keys) {
                $sections[$section][is_string($keys) ? $keys : $field] = is_string($keys)
                    ? (string) ($input['sections'][$section][$keys] ?? '')
                    : array_map(
                        fn ($item) => array_combine($keys, array_map(fn ($key) => (string) ($item[$key] ?? ''), $keys)),
                        array_values($input['sections'][$section][$field] ?? []),
                    );
            }
        }

        return [
            'section_order' => array_values($input['section_order']),
            'section_visibility' => array_map(fn ($key) => (bool) $input['section_visibility'][$key], array_combine(array_keys(self::SCHEMA), array_keys(self::SCHEMA))),
            'sections' => $sections,
        ];
    }
}

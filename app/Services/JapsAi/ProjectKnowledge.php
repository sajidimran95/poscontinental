<?php

namespace App\Services\JapsAi;

use App\Support\AppFeatures;

/**
 * Fixed product map for Continental / JAPS wholesale POS (this codebase only).
 * Used by free local answers + OpenAI system context.
 */
class ProjectKnowledge
{
    /**
     * Human-readable module guide (no live numbers).
     */
    public static function systemGuide(): string
    {
        $lines = [
            '# Continental / JAPS wholesale POS — product map (this project only)',
            '',
            'This app is a multi-company wholesale POS: inventory, sales, purchasing, tobacco/MSA filing, reports, and admin.',
            'POS AI answers only about **this product for the logged-in company** — not general knowledge or other software.',
            '',
            '## Where things live (menus)',
        ];

        $byGroup = [];
        foreach (AppFeatures::all() as $key => $meta) {
            $group = (string) ($meta['group'] ?? 'Other');
            $label = (string) ($meta['label'] ?? $key);
            $byGroup[$group][] = $label.' (`'.$key.'`)';
        }

        $order = ['File', 'Inquiry', 'Inventory', 'Sales', 'Purchasing', 'Reports', 'Other'];
        foreach ($order as $group) {
            if (empty($byGroup[$group])) {
                continue;
            }
            $lines[] = '### '.$group;
            foreach ($byGroup[$group] as $row) {
                $lines[] = '- '.$row;
            }
            unset($byGroup[$group]);
        }
        foreach ($byGroup as $group => $rows) {
            $lines[] = '### '.$group;
            foreach ($rows as $row) {
                $lines[] = '- '.$row;
            }
        }

        $lines[] = '';
        $lines[] = '## Typical workflows';
        $lines[] = '- **Sell:** Customers → Sales Orders (scan/add items) → Invoice → Payments';
        $lines[] = '- **Buy:** Suppliers → Purchase Orders → Inventory Receiving → stock increases; RTV returns to vendor';
        $lines[] = '- **Stock:** Items list (stock adjust + inventory journal track), Stock Counts, Stock Status, Item Velocity';
        $lines[] = '- **Pricing:** Item form + Bulk Pricing + customer price levels';
        $lines[] = '- **Credits:** Credit memos (optional restock)';
        $lines[] = '- **Tobacco / MSA:** Stamp inventory + MSA / tobacco filing reports (week periods)';
        $lines[] = '- **Reports:** Sales / purchase reports, price list, MSA product sales file';
        $lines[] = '- **Admin:** Company settings, users & roles, email setup, overselling, POS AI settings';
        $lines[] = '';
        $lines[] = '## POS AI usage';
        $lines[] = '- **Suggested questions:** free, live DB (no OpenAI credits).';
        $lines[] = '- **Typed free-form:** optional OpenAI; must stay on this POS project; needs API key + credits.';
        $lines[] = '- Refuse off-topic questions (weather, other companies, general coding help, etc.).';

        return implode("\n", $lines);
    }

    /**
     * Compact JSON for OpenAI (menus + workflows).
     *
     * @return array<string, mixed>
     */
    public static function systemMapArray(): array
    {
        $modules = [];
        foreach (AppFeatures::all() as $key => $meta) {
            $group = (string) ($meta['group'] ?? 'Other');
            $modules[$group][] = [
                'feature' => $key,
                'label' => (string) ($meta['label'] ?? $key),
            ];
        }

        return [
            'product' => 'Continental / JAPS wholesale POS',
            'scope' => 'Logged-in company data and this application only',
            'modules_by_menu' => $modules,
            'workflows' => [
                'sales' => 'Sales Orders → Invoice → Payments / Credit Memos',
                'purchasing' => 'Purchase Orders → Receiving → RTV',
                'inventory' => 'Items, stock adjust + journal track, stock counts, bulk pricing',
                'tobacco' => 'Stamp inventory, MSA / tobacco filing reports',
                'admin' => 'Company, users/roles, email, overselling, POS AI',
            ],
        ];
    }
}

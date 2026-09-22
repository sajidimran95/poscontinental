<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\CompanyMailConfig;
use App\Services\JapsAi\PosAiIntelligenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendPosAiDigest extends Command
{
    protected $signature = 'pos-ai:digest {--company= : Send only this company id}';

    protected $description = 'Email the POS AI morning digest to companies that opted in';

    public function handle(): int
    {
        $query = Company::query()->where('is_active', true)->where('japs_ai_digest_enabled', true);
        if ($this->option('company')) {
            $query->where('id', (int) $this->option('company'));
        }

        $sent = 0;
        foreach ($query->get() as $company) {
            $to = trim((string) $company->email);
            if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $this->warn($company->name.': no company email, skipped.');

                continue;
            }

            try {
                $body = PosAiIntelligenceService::forCompany((int) $company->id)->digestText();
                $body .= "\n\nThis note is a draft for staff. No purchase order or collection email was sent.";
                CompanyMailConfig::apply($company);
                Mail::raw($body, function ($message) use ($company, $to) {
                    $message->to($to)
                        ->subject('POS AI morning digest — '.$company->name.' — '.now()->format('M j, Y'));
                });
                $sent++;
                $this->info('Sent digest to '.$to.' for '.$company->name.'.');
            } catch (\Throwable $e) {
                report($e);
                $this->error($company->name.': '.$e->getMessage());
            }
        }

        $this->info($sent.' digest'.($sent === 1 ? '' : 's').' sent.');

        return self::SUCCESS;
    }
};

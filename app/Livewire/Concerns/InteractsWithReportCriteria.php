<?php

namespace App\Livewire\Concerns;

/**
 * Shared Report Criteria (date + cancel-returns-to-source) for POS reports.
 */
trait InteractsWithReportCriteria
{
    public bool $showCriteria = true;

    public bool $reportReady = false;

    /** single | range — default single Date */
    public string $dateMode = 'single';

    public string $singleDate = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $returnUrl = '';

    public function bootInteractsWithReportCriteria(): void
    {
        // Named boot method is auto-called by Livewire when trait is used.
    }

    protected function initReportCriteria(?string $reportRouteName = null): void
    {
        $this->fillTodayDates();
        $this->returnUrl = $this->resolveReturnUrl($reportRouteName);
    }

    protected function resolveReturnUrl(?string $reportRouteName = null): string
    {
        $previous = url()->previous();
        $home = route('home');

        try {
            $reportUrl = $reportRouteName && \Illuminate\Support\Facades\Route::has($reportRouteName)
                ? route($reportRouteName)
                : url()->current();
        } catch (\Throwable) {
            $reportUrl = url()->current();
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $base = $appUrl !== '' ? $appUrl : url('/');

        if (
            $previous
            && $previous !== $reportUrl
            && ! str_starts_with($previous, $reportUrl.'?')
            && str_starts_with($previous, $base)
        ) {
            return $previous;
        }

        return $home;
    }

    public function fillTodayDates(bool $onlyEmpty = false): void
    {
        $today = now()->toDateString();

        if (! $onlyEmpty || $this->singleDate === '') {
            $this->singleDate = $today;
        }
        if (! $onlyEmpty || $this->dateFrom === '') {
            $this->dateFrom = $today;
        }
        if (! $onlyEmpty || $this->dateTo === '') {
            $this->dateTo = $today;
        }
    }

    public function openCriteria(): void
    {
        $this->fillTodayDates(onlyEmpty: $this->reportReady);
        $this->resetErrorBag();
        $this->showCriteria = true;
    }

    public function cancelCriteria(): void
    {
        $this->resetErrorBag();

        if (! $this->reportReady) {
            $url = $this->returnUrl !== '' ? $this->returnUrl : route('home');
            $this->redirect($url, navigate: true);

            return;
        }

        $this->showCriteria = false;
    }

    /**
     * Normalize date window from criteria. Call at start of applyCriteria in each report.
     *
     * @return array{0: string, 1: string} [from, to] Y-m-d
     */
    protected function resolveDateWindow(): array
    {
        $this->fillTodayDates(onlyEmpty: true);

        $mode = $this->dateMode === 'range' ? 'range' : 'single';
        $this->dateMode = $mode;

        if ($mode === 'single') {
            if ($this->singleDate === '') {
                $this->singleDate = now()->toDateString();
            }
            $this->dateFrom = $this->singleDate;
            $this->dateTo = $this->singleDate;
        } else {
            if ($this->dateFrom === '') {
                $this->dateFrom = now()->toDateString();
            }
            if ($this->dateTo === '') {
                $this->dateTo = $this->dateFrom;
            }
            if ($this->dateFrom > $this->dateTo) {
                [$this->dateFrom, $this->dateTo] = [$this->dateTo, $this->dateFrom];
            }
        }

        return [$this->dateFrom, $this->dateTo];
    }

    public function periodLabel(): string
    {
        if ($this->dateFrom === '' || $this->dateTo === '') {
            return '—';
        }

        try {
            $from = \Carbon\Carbon::parse($this->dateFrom)->format('n/j/Y');
            $to = \Carbon\Carbon::parse($this->dateTo)->format('n/j/Y');
        } catch (\Throwable) {
            return $this->dateFrom.' – '.$this->dateTo;
        }

        return $this->dateFrom === $this->dateTo ? $from : $from.' – '.$to;
    }

    protected function requireReportReady(): bool
    {
        if (! $this->reportReady) {
            $this->openCriteria();

            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    protected function streamReportCsv(string $basename, array $headers, array $rows): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = $basename.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  array{title: string, period?: string, sections: list<array<string, mixed>>}  $payload
     */
    protected function streamReportPdf(
        \App\Services\DocumentPdfService $pdfs,
        array $payload,
        string $basename,
        string $orientation = 'landscape'
    ): \Symfony\Component\HttpFoundation\StreamedResponse {
        return $pdfs->streamDownload(
            $pdfs->posReportPdf($payload, auth()->user(), $orientation),
            $basename.'-'.now()->format('Ymd-His').'.pdf'
        );
    }

    protected function money($n): string
    {
        return number_format((float) $n, 2, '.', '');
    }

    protected function moneyLabel($n): string
    {
        return '$'.number_format((float) $n, 2);
    }
}

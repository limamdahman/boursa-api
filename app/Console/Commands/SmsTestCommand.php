<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sms\SmsDriver;
use Illuminate\Console\Command;

/**
 * Testbefehl: sendet eine Test-SMS über den konfigurierten Treiber.
 */
final class SmsTestCommand extends Command
{
    protected $signature = 'sms:test {phone : Zielnummer im E.164-Format}';

    protected $description = 'Sendet eine Test-SMS über den konfigurierten SMS-Treiber';

    public function handle(SmsDriver $sms): int
    {
        $phone = (string) $this->argument('phone');

        $this->info(sprintf('Treiber: %s', config('services.sms.driver')));
        $this->info(sprintf('Sende Test-SMS an %s ...', $phone));

        $ok = $sms->send($phone, 'Boursa: test SMS / رسالة تجريبية');

        if ($ok) {
            $this->info('OK - SMS vom Provider akzeptiert.');

            return self::SUCCESS;
        }

        $this->error('FEHLER - siehe storage/logs/laravel.log');

        return self::FAILURE;
    }
}

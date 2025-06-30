<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use App\Models\Period;
use Illuminate\Console\Command;

class SetSiteConfig extends Command
{
    protected $signature = 'site:config {key} {value?}';
    protected $description = 'Set or get site configuration values';

    public function handle()
    {
        $key = $this->argument('key');
        $value = $this->argument('value');

        if ($value === null) {
            // Mostrar el valor actual
            $currentValue = SiteSetting::getValue($key);
            $this->info("Current value for '{$key}': " . ($currentValue ?? 'null'));
            return;
        }

        // Establecer el nuevo valor
        $setting = SiteSetting::where('key', $key)->first();
        
        if ($setting) {
            $setting->update(['value' => $value]);
            $this->info("Updated '{$key}' to: {$value}");
        } else {
            $this->error("Setting '{$key}' not found. Available settings:");
            $settings = SiteSetting::all(['key', 'label']);
            foreach ($settings as $setting) {
                $this->line("  {$setting->key} - {$setting->label}");
            }
        }
    }
}

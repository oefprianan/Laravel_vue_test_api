<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateJWTSecret extends Command
{
    protected $signature = 'jwt:secret';
    protected $description = 'Generate JWT secret key';

    public function handle()
    {
        try {
            // สร้าง complex key
            $secret = base64_encode(
                hash_hmac(
                    'sha256',
                    Str::random(64) . time(),
                    Str::random(32),
                    true
                )
            );

            // อัพเดทไฟล์ .env
            $this->updateEnvironmentFile($secret);

            $this->info('JWT secret generated successfully!');
            $this->info('New JWT secret: ' . $secret);
            $this->info('Please restart your server to apply changes.');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error generating JWT secret: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function updateEnvironmentFile($secret)
    {
        $envFile = base_path('.env');

        if (!file_exists($envFile)) {
            throw new \Exception('.env file not found');
        }

        $contents = file_get_contents($envFile);

        if (strpos($contents, 'JWT_SECRET') !== false) {
            // อัพเดท existing key
            file_put_contents($envFile, preg_replace(
                '/JWT_SECRET=.*/',
                'JWT_SECRET=' . $secret,
                $contents
            ));
        } else {
            // เพิ่ม key ใหม่
            file_put_contents($envFile, $contents . "\nJWT_SECRET=" . $secret);
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateTokenFormats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:update-formats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update token_format column for existing tokens to show Bearer format';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tokens = DB::table('personal_access_tokens')
            ->where(function($query) {
                $query->whereNull('token_format')
                      ->orWhere('token_format', '')
                      ->orWhereNull('plain_token')
                      ->orWhere('plain_token', '');
            })
            ->get();

        $updated = 0;
        foreach ($tokens as $token) {
            // Note: We can't reconstruct the plain token from the hash
            // This command only updates token_format for existing tokens
            // New tokens will have both plain_token and token_format set automatically
            $tokenPreview = substr($token->token, 0, 10) . '...';
            $tokenFormat = "Bearer {$token->id}|{$tokenPreview}";
            
            $updateData = ['token_format' => $tokenFormat];
            
            DB::table('personal_access_tokens')
                ->where('id', $token->id)
                ->update($updateData);
            
            $updated++;
        }

        $this->info("Updated {$updated} token(s) with Bearer format.");
        $this->warn("Note: plain_token can only be set for new tokens. Existing tokens cannot be reconstructed.");
        return 0;
    }
}

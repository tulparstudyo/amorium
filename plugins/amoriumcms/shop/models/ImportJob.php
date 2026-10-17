<?php namespace AmoriumCms\Shop\Models;

use Model;
use Backend\Models\User;
use Illuminate\Support\Facades\Artisan;

/**
 * ImportJob is one product link waiting for shop:import-product.
 */
class ImportJob extends Model
{
    const PENDING = 'pending';
    const PROCESSING = 'processing';
    const COMPLETED = 'completed';
    const FAILED = 'failed';

    public $table = 'amoriumcms_shop_import_jobs';

    protected $fillable = ['url', 'site_id', 'category_id', 'status', 'backend_user_id'];

    protected $casts = [
        'site_id' => 'integer',
        'category_id' => 'integer',
        'exit_code' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public $belongsTo = [
        'backend_user' => [User::class, 'key' => 'backend_user_id'],
        'category' => [Category::class, 'key' => 'category_id'],
    ];

    /**
     * execute runs shop:import-product and stores the command result on this job
     */
    public function execute(): void
    {
        try {
            $parameters = ['url' => $this->url];

            if ($this->site_id) {
                $parameters['--site'] = (string) $this->site_id;
            }

            if ($this->category_id) {
                $parameters['--category'] = (string) $this->category_id;
            }

            $exit = Artisan::call('shop:import-product', $parameters);
            $this->exit_code = $exit;
            $this->result = static::clip(Artisan::output());
            $this->status = $exit === 0 ? self::COMPLETED : self::FAILED;
        } catch (\Throwable $exception) {
            $this->exit_code = 1;
            $this->result = static::clip($exception->getMessage());
            $this->status = self::FAILED;
        }

        $this->finished_at = now();
        $this->save();
    }

    /**
     * label is the English status name shown in the admin
     */
    public function label(): string
    {
        return match ($this->status) {
            self::PROCESSING => 'Running',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            default => 'Pending',
        };
    }

    /**
     * clip
     */
    protected static function clip(string $text): string
    {
        $text = trim($text);

        return mb_strlen($text) > 65000 ? mb_substr($text, 0, 65000) : $text;
    }
}

<?php

/**
 * Eloquent implementation of PaymentRepository contract.
 *
 * Provides database persistence for payment records using the configured Eloquent model.
 * Resolves the model class from config automatically.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Repositories
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Repositories;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class EloquentPaymentRepository implements PaymentRepository
{
    protected string $modelClass;

    private ?string $orderColumn = null;

    private ?string $registrationColumn = null;

    public function __construct(?string $modelClass = null)
    {
        $this->modelClass = $modelClass ?? (config('stag-herd.payment_model') ?? '');

        if ($this->modelClass === '') {
            throw new RuntimeException('stag-herd.payment_model is not configured');
        }

        if (!class_exists($this->modelClass)) {
            throw new RuntimeException('Payment model class not found: ' . $this->modelClass);
        }

        if (!is_subclass_of($this->modelClass, Model::class)) {
            throw new RuntimeException('Payment model must extend ' . Model::class);
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function query()
    {
        $class = $this->modelClass;

        return $class::query();
    }

    public function find(int|string $id): ?object
    {
        return $this->query()->find($id);
    }

    public function findByMethodId(
        string $method,
        string $methodId,
    ): ?object {
        return $this->query()
            ->where('method', $method)
            ->where('method_id', $methodId)
            ->first();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): object
    {
        return DB::transaction(function () use ($data) {
            $class = $this->modelClass;

            return $class::create($data);
        });
    }

    public function save(object $paymentModel): object
    {
        if ($paymentModel instanceof Model) {
            DB::transaction(function () use ($paymentModel) {
                $paymentModel->save();
            });

            return $paymentModel;
        }

        throw new InvalidArgumentException('Payment model is not an Eloquent instance');
    }

    /**
     * Deletes payments lacking an order reference.
     *
     * @return int  Number of deleted payment records.
     */
    public function deleteOrphans(): int
    {
        return $this->query()
            ->whereNull($this->getOrderColumn())
            ->delete();
    }

    /**
     * Returns pending payments in the given registration range optionally filtered by method.
     *
     * @param  CarbonInterface|null $from     Lower bound (inclusive) for registration timestamp.
     * @param  CarbonInterface|null $to       Upper bound (inclusive) for registration timestamp.
     * @param  array<int, string>   $methods  Payment method codes to include; empty for all.
     * @return LazyCollection<object>         Lazy collection of pending payment models.
     */
    public function pendingPayments(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        array $methods = []
    ): LazyCollection {
        return $this->query()
            ->where('status', PaymentStatus::PENDING->value)
            ->when($methods !== [], function ($query) use ($methods) {
                $query->whereIn('method', $methods);
            })
            ->when($from, function ($query) use ($from) {
                $query->where($this->getRegistrationColumn(), '>=', $from);
            })
            ->when($to, function ($query) use ($to) {
                $query->where($this->getRegistrationColumn(), '<=', $to);
            })
            ->orderBy($this->getRegistrationColumn())
            ->lazy();
    }

    /**
     * Marks pending payments older than the threshold with the provided status.
     *
     * @param  CarbonInterface $threshold  Timestamp limit; payments older are updated.
     * @param  string          $status     Status to apply to stale payments.
     * @return int                         Number of updated payment records.
     */
    public function cancelPendingBefore(CarbonInterface $threshold, string $status): int
    {
        $updates = [
            'status' => $status,
        ];

        if ($this->hasColumn('dt_executed')) {
            $updates['dt_executed'] = Carbon::now();
        }

        return $this->query()
            ->where('status', PaymentStatus::PENDING->value)
            ->where($this->getRegistrationColumn(), '<', $threshold)
            ->update($updates);
    }

    private function getOrderColumn(): string
    {
        if (!is_null($this->orderColumn)) {
            return $this->orderColumn;
        }

        $this->orderColumn = $this->hasColumn('id_order') ? 'id_order' : 'order_id';

        if (!$this->hasColumn($this->orderColumn)) {
            $this->orderColumn = 'id_order';
        }

        return $this->orderColumn;
    }

    private function getRegistrationColumn(): string
    {
        if (!is_null($this->registrationColumn)) {
            return $this->registrationColumn;
        }

        $candidates = [
            config('stag-herd.cleanup.timestamp_column', 'dt_registration'),
            'dt_registration',
            'created_at',
        ];

        foreach ($candidates as $column) {
            if ($this->hasColumn($column)) {
                $this->registrationColumn = $column;

                return $this->registrationColumn;
            }
        }

        $this->registrationColumn = 'dt_registration';

        return $this->registrationColumn;
    }

    private function hasColumn(string $column): bool
    {
        try {
            return Schema::hasColumn($this->getTableName(), $column);
        } catch (Throwable) {
            return false;
        }
    }

    private function getTableName(): string
    {
        $class = $this->modelClass;

        /** @var Model $model */
        $model = new $class();

        return $model->getTable();
    }
}

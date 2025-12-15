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

use Equidna\StagHerd\Contracts\PaymentRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class EloquentPaymentRepository implements PaymentRepository
{
    protected string $modelClass;

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
}

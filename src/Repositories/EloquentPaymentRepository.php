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
use InvalidArgumentException;
use RuntimeException;

class EloquentPaymentRepository implements PaymentRepository
{
    protected string $modelClass;

    public function __construct(?string $modelClass = null)
    {
        $this->modelClass = $modelClass ?? config('stag-herd.payment_model');

        if (!$this->modelClass || !class_exists($this->modelClass)) {
            throw new RuntimeException(
                'Payment model class not found. Please configure stag-herd.payment_model'
            );
        }
    }

    protected function query()
    {
        $class = $this->modelClass;

        return $class::query();
    }

    public function find(int|string $id)
    {
        return $this->query()->find($id);
    }

    public function findByMethodId(
        string $method,
        string $methodId,
    ) {
        return $this->query()
            ->where('method', $method)
            ->where('method_id', $methodId)
            ->first();
    }

    public function create(array $data)
    {
        $class = $this->modelClass;

        return $class::create($data);
    }

    public function save($paymentModel)
    {
        if ($paymentModel instanceof Model) {
            return $paymentModel->save();
        }

        throw new InvalidArgumentException('Payment model is not an Eloquent instance');
    }
}

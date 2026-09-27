<?php

declare(strict_types=1);

namespace Andriichuk\CashierBlueSnap;

use Andriichuk\BlueSnap\BlueSnapClient;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;

final class Cashier
{
    /** @var class-string<Customer> */
    public static string $customerModel = Customer::class;

    /** @var class-string<Subscription> */
    public static string $subscriptionModel = Subscription::class;

    /** @var class-string<Transaction> */
    public static string $transactionModel = Transaction::class;

    public static function client(): BlueSnapClient
    {
        return Container::getInstance()->make(BlueSnapClient::class);
    }

    /** @param class-string<Customer> $model */
    public static function useCustomerModel(string $model): void
    {
        self::$customerModel = $model;
    }

    /** @param class-string<Subscription> $model */
    public static function useSubscriptionModel(string $model): void
    {
        self::$subscriptionModel = $model;
    }

    /** @param class-string<Transaction> $model */
    public static function useTransactionModel(string $model): void
    {
        self::$transactionModel = $model;
    }

    /**
     * @param class-string<Model> $model
     */
    public static function newModel(string $model): Model
    {
        return new $model();
    }
}

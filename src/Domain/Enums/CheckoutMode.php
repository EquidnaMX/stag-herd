<?php

namespace Equidna\StagHerd\Domain\Enums;

enum CheckoutMode: string
{
    case PAYMENT = 'payment';
    case SUBSCRIPTION = 'subscription';
}

<?php

declare(strict_types=1);

namespace App\Enums;

enum MetaEventType: string
{
    case VIEW_CONTENT = 'ViewContent';
    case SEARCH = 'Search';
    case LEAD = 'Lead';
    case ADD_TO_WISHLIST = 'AddToWishlist';
    case COMPLETE_REGISTRATION = 'CompleteRegistration';
    case LOGIN = 'Login';
    case SUBMIT_APPLICATION = 'SubmitApplication';
}

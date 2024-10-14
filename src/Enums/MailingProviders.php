<?php

namespace Dashed\DashedForms\Enums;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedForms\Classes\MailingProviders\ActiveCampaign;

enum MailingProviders: string
{
    case ActiveCampaign = 'activeCampaign';

    public function getClass(string $siteId = '')
    {
        if (! $siteId) {
            $siteId = Sites::getFirstSite()['id'];
        }

        $adapter = match ($this) {
            self::ActiveCampaign => new ActiveCampaign($siteId),
            default => null,
        };

        if (! $adapter) {
            return null;
        }

        return $adapter;
    }
}

<?php

namespace Webkul\Installer\Listeners;

use GuzzleHttp\Client;
use Webkul\User\Repositories\UserRepository;

class Installer
{
    /**
     * Api endpoint
     *
     * @var string
     */
    protected const API_ENDPOINT = 'https://updates.krayincrm.com/api/updates';

    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(protected UserRepository $userRepository) {}

    /**
     * After Krayin is successfully installed
     *
     * The call is skipped entirely unless telemetry is enabled, see
     * config/telemetry.php. The listener stays registered either way so that
     * enabling the flag does not require touching the service provider.
     *
     * @return void
     */
    public function installed()
    {
        if (! config('telemetry.enabled')) {
            return;
        }

        $user = $this->userRepository->first();

        $httpClient = new Client;

        try {
            $httpClient->request('POST', self::API_ENDPOINT, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'domain' => config('app.url'),
                    'email' => $user?->email,
                    'name' => $user?->name,
                    'country_code' => config('app.default_country') ?? 'IN',
                ],
            ]);
        } catch (\Exception $e) {
            /**
             * Skip the error
             */
        }
    }
}

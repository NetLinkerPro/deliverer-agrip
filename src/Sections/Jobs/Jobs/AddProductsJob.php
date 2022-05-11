<?php


namespace NetLinker\DelivererAgrip\Sections\Jobs\Jobs;

use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Targets\Services\AddProducts\AddProducts;
use NetLinker\FairQueue\Sections\JobStatuses\Models\JobStatus;
use NetLinker\FairQueue\Sections\JobStatuses\Traits\Ownerable;
use NetLinker\FairQueue\Sections\JobStatuses\Traits\Trackable;
use NetLinker\WideStore\Sections\Shops\Models\Shop;

class AddProductsJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Trackable, Ownerable;

    /** @var array $params */
    public $params;

    /** @var int modelId */
    public $modelId;

    /** @var int $tries */
    public $tries = 1;

    /** @var int $timeout */
    public $timeout = 6080000;

    /**
     * Constructor
     *
     * @param $accountId
     * @param array $params
     */
    public function __construct($params = [])
    {
        $this->setOwnerUuid($params['setting']['value']['owner_supervisor_uuid']);
        $this->auth($params['setting']['value']['owner_supervisor_uuid']);
        $this->prepareStatus(['name' =>  __('deliverer-agrip::jobs.add_products', ['name' => config('deliverer-agrip.name')])]);
        $this->prepareAuthUserJob($params['setting']['value']['owner_supervisor_uuid']);
        $this->params = $params;
        $this->setInput([
            'params' => $this->params,
        ]);
        $this->setExternalUuid('agrip');
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \NetLinker\DelivererAgrip\Exceptions\DelivererAgripException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function handle()
    {
        if ($this->isCanceled()){
            $this->setOutput([
                'canceled' => true,
            ]);
            return;
        }

        $this->loginUserJob();
        $this->setProgressMax(10);

        DelivererLogger::listen(function($message){
            $this->addLog($message);
        });

        $service = new AddProducts(function($message){
            $this->addLog($message);
        });

        $steps = $service->addProducts();

        foreach ($steps as $step){

            // interrupt
            if ($this->isInterrupt()){
                break;
            }

            if ($this->isCanceled()){
                $this->setOutput([
                    'canceled' => true,
                ]);
                return;
            }

            if ($step['log'] ?? false){
                $this->addLog($step['log']);
            }

            if ($step['progress_max'] ?? false){
                $this->setProgressMax($step['progress_max']);
            }

            if ($step['progress_now'] ?? false){
                $this->setProgressNow($step['progress_now']);
            }
        }

        $this->setOutput(['status' => 'success']);
        $this->setProgressNow($this->progressMax);

       $this->addShopProducts();
    }

    /**
     * The job failed to process.
     *
     * @param  Exception  $exception
     * @return void
     */
    public function failed(Exception $exception)
    {
        dump($exception->getTraceAsString());
        $this->update([
            'status' => JobStatus::STATUS_FAILED,
            'finished_at' => Carbon::now(),
            'error' => $exception->getMessage() . PHP_EOL . $exception->getTraceAsString(),
        ]);
    }

    private function addShopProducts()
    {
        $queue = $this->params['queues']['add_shop_products'] ?? null;

        if ($queue){

            $shops = Shop::where('deliverer', 'agrip')->get();

            foreach ($shops as $shop){

                AddShopProductsJob::dispatch([
                    'shop_uuid' => $shop->uuid,
                    'owner_uuid' => $shop->owner_uuid,
                    'configuration_uuid' => $shop->configuration_uuid
                ])->onQueue($queue);
            }

        }
    }


    /**
     * Auth
     *
     * @param $ownerUuid
     */
    private function auth($ownerUuid)
    {
        if (Auth::check()){
            return;
        }

        $model = config('deliverer-agrip.model');
        $user = $model::where('owner_uuid', $ownerUuid)->firstOrFail();

        Auth::login($user);
    }
}
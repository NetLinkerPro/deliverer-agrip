<?php


namespace NetLinker\DelivererAgrip\Sections\Jobs\Jobs;

use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;
use NetLinker\DelivererAgrip\Sections\Targets\Services\AddShopProducts\AddShopProducts;
use NetLinker\DelivererAgrip\Sections\Targets\Services\UpdateShopProducts\UpdateShopProducts;
use NetLinker\FairQueue\Sections\JobStatuses\Models\JobStatus;
use NetLinker\FairQueue\Sections\JobStatuses\Traits\Ownerable;
use NetLinker\FairQueue\Sections\JobStatuses\Traits\Trackable;
use NetLinker\WideStore\Sections\Shops\Models\Shop;

class UpdateShopProductsJob implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Trackable, Ownerable;

    /** @var array $params */
    public $params;

    /** @var int modelId */
    public $modelId;

    /** @var int $tries */
    public $tries = 1;

    /** @var int $timeout */
    public $timeout = 10800;

    /**
     * Constructor
     *
     * @param $accountId
     * @param array $params
     */
    public function __construct($params = [])
    {
        $this->setOwnerUuid($params['owner_uuid']);
        $this->prepareStatus(['name' =>  __('deliverer-agrip::jobs.update_shop_products', ['name' => config('deliverer-agrip.name')])]);
        $this->prepareAuthUserJob($params['owner_uuid']);
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

        $service = new UpdateShopProducts($this->params['shop_uuid'], $this->params['owner_uuid'], $this->params['configuration_uuid']);

        $steps = $service->updateShopProducts();

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
}
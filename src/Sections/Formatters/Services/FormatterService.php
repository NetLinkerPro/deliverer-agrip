<?php


namespace NetLinker\DelivererAgrip\Sections\Formatters\Services;


use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Formatters\IAction;

class FormatterService
{
    /** @var array $rangesActions */
    protected $rangesActions;

    /**
     * Constructor
     *
     * @param array $rangesActions
     */
    public function __construct(array $rangesActions)
    {
        $this->rangesActions = $rangesActions;
    }

    /**
     * Format
     *
     * @param mixed $value
     * @param string $range
     * @param array $params
     * @return string
     * @throws DelivererAgripException
     */
    public function format($value, string $range, array $params=[])
    {

        $value = $this->formatByRange($value, $range, $params);

        return $this->formatByRange($value, 'each_after', $params);
    }

    /**
     * Format by range
     *
     * @param $value
     * @param $range
     * @param $params
     * @return mixed
     * @throws DelivererAgripException
     */
    public function formatByRange($value, $range, $params)
    {
        $actions = $this->getActions($range);

        if (!$actions) {
            return $value;
        }

        foreach ($actions as $action) {

            $classAction = $this->getClassAction($range, $action['action']);

            if (!$classAction) {
                continue;
            }

            if (class_exists($classAction)) {

                /** @var IAction $iAction */
                $iAction = new $classAction;

                $configuration = $this->getActionConfiguration($action);

                $value = $iAction->action($value, $configuration, $params);
            }

        }

        return $value;
    }

    /**
     * Get actions
     *
     * @param string $range
     * @return array|mixed
     */
    private function getActions(string $range)
    {
        return $this->rangesActions[$range] ?? [];
    }

    /**
     * Get class action
     *
     * @param string $range
     * @param $action
     * @return string
     */
    private function getClassAction(string $range, $action)
    {
        return sprintf('NetLinker\DelivererAgrip\Formatters\%s\%s', Str::studly($range), Str::studly($action));
    }

    /**
     * Get action configuration
     *
     * @param array $action
     * @return mixed
     * @throws DelivererAgripException
     */
    private function getActionConfiguration(array $action)
    {
        $contentConfiguration = $action['configuration'];

        if (!$contentConfiguration){
            return [];
        }

        $configuration = json_decode(str_replace(["\n", "\r"], '', $action['configuration']), true, 512, JSON_UNESCAPED_UNICODE);

        $error = json_last_error();
        if ($configuration === null && $error !== JSON_ERROR_NONE) {
            throw new DelivererAgripException('Error parse configuration agrip ' . $error);
        }
        return $configuration ?? [];
    }
}
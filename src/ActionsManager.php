<?php

namespace Terranet\Administrator;

use Terranet\Administrator\Actions\Collection;
use Terranet\Administrator\Contracts\ActionsManager as ActionsManagerContract;
use Terranet\Administrator\Contracts\Module;
use Terranet\Administrator\Contracts\Services\CrudActions;

class ActionsManager implements ActionsManagerContract
{
    /**
     * @var CrudActions
     */
    protected $service;

    /**
     * @var Module
     */
    protected $module;

    /**
     * List of item-related actions.
     *
     * @var array
     */
    protected $actions = null;

    /**
     * List of global actions.
     *
     * @var array
     */
    protected $globalActions = null;

    /**
     * Check if resource is readonly - has no actions.
     *
     * @var null|bool
     */
    protected $readonly;

    public function __construct(CrudActions $service, Module $module)
    {
        $this->service = $service;

        $this->module = $module;
    }

    /**
     * Fetch module's single (per item) actions.
     *
     * @return Collection
     */
    public function actions()
    {
        return $this->scaffoldActions();
    }

    /**
     * Fetch module's batch actions.
     *
     * @return Collection
     */
    public function batch()
    {
        return $this->scaffoldBatch();
    }

    /**
     * Parse handler class for per-item and global actions.
     *
     * @return Collection
     */
    protected function scaffoldActions()
    {
        return (new Collection($this->service->actions()));
    }

    /**
     * Parse handler class for per-item and global actions.
     *
     * @return Collection
     */
    protected function scaffoldBatch()
    {
        return (new Collection($this->service->batchActions()));
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @param string $action
     * @param $model
     *
     * @return bool
     */
    public function authorize($action, $model = null)
    {
        # for most cases it is enough to set
        # permissions in Resource object.
        if (method_exists($this->module, 'authorize')) {
            return $this->module->authorize($action, $model);
        }

        # Ask Actions Service for action permissions.
        return $this->service->authorize($action, $model, $this->module);
    }

    /**
     * check if resource has no Actions at all.
     */
    public function readonly()
    {
        if (null === $this->readonly) {
            # check for <Resource>::hideActions() method.
            if (method_exists($this->module, 'readonly')) {
                $this->readonly = $this->module->readonly();
            }

            # check for <Actions>::readonly() method.
            elseif (method_exists($this->service, 'readonly')) {
                $this->readonly = $this->service->readonly();
            }

            # allow actions if no other policy defined
            else {
                $this->readonly = false;
            }
        }

        return $this->readonly;
    }

    /**
     * Call handler method.
     *
     * @param       $method
     * @param array $arguments
     *
     * @return mixed
     */
    public function exec($method, array $arguments = [])
    {
        // execute custom action
        if (starts_with($method, 'action::')) {
            $handler = $this->scaffoldActions()->find(
                str_replace('action::', '', $method)
            );

            return call_user_func_array([$handler, 'handle'], $arguments);
        }

        // Execute batch action
        if (starts_with($method, 'batch::')) {
            $handler = $this->scaffoldBatch()->find(
                str_replace('batch::', '', $method)
            );

            return call_user_func_array([$handler, 'handle'], $arguments);
        }

        // Execute CRUD action
        return call_user_func_array([$this->service, $method], (array)$arguments);
    }
}

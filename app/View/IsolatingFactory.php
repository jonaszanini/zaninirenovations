<?php

namespace App\View;

use App\Support\AdminInfoTipInclude;
use Illuminate\View\Factory;

class IsolatingFactory extends Factory
{
    public static function decorate(Factory $factory): self
    {
        if ($factory instanceof self) {
            return $factory;
        }

        $isolated = new self(
            $factory->getEngineResolver(),
            $factory->getFinder(),
            $factory->getDispatcher()
        );
        $isolated->setContainer($factory->getContainer());
        foreach ($factory->getShared() as $key => $value) {
            $isolated->share($key, $value);
        }
        $isolated->share('__env', $isolated);

        $ref = new \ReflectionClass(Factory::class);
        foreach (['extensions', 'aliases', 'composers'] as $prop) {
            if (! $ref->hasProperty($prop)) {
                continue;
            }
            $property = $ref->getProperty($prop);
            $property->setAccessible(true);
            $property->setValue($isolated, $property->getValue($factory));
        }

        return $isolated;
    }

    /**
     * @param  string  $view
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $data
     * @param  array  $mergeData
     * @return \Illuminate\Contracts\View\View
     */
    public function make($view, $data = [], $mergeData = [])
    {
        $name = is_string($view) ? $this->normalizeName($view) : $view;
        if (in_array($name, [AdminInfoTipInclude::INFO_TIP_VIEW, AdminInfoTipInclude::FIELD_HINT_VIEW, AdminInfoTipInclude::TIPPED_LABEL_VIEW], true)) {
            $mergeData = [];
            $parsed = $this->parseData($data);
            if (is_array($parsed)) {
                AdminInfoTipInclude::assertNoLegacyKeys($parsed);
            }
        }

        return parent::make($view, $data, $mergeData);
    }
}

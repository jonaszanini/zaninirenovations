<?php

if (! function_exists('app_path')) {
    function app_path($path = '')
    {
        return app()->path($path);
    }
}

if (! function_exists('base_path')) {
    function base_path($path = '')
    {
        return app()->basePath($path);
    }
}

if (! function_exists('config_path')) {
    function config_path($path = '')
    {
        return app()->basePath('config'.($path ? DIRECTORY_SEPARATOR.$path : $path));
    }
}

if (! function_exists('public_path')) {
    function public_path($path = '')
    {
        return app()->basePath('public'.($path ? DIRECTORY_SEPARATOR.$path : $path));
    }
}

if (! function_exists('resource_path')) {
    function resource_path($path = '')
    {
        return app()->basePath('resources'.($path ? DIRECTORY_SEPARATOR.$path : $path));
    }
}

if (! function_exists('storage_path')) {
    function storage_path($path = '')
    {
        return app()->basePath('storage'.($path ? DIRECTORY_SEPARATOR.$path : $path));
    }
}


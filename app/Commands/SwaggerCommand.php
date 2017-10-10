<?php
namespace App\Commands;

use Illuminate\Console\Command;

class SwaggerCommand extends Command
{
    protected $signature = 'swagger';
    
    protected $description = 'generate swagger docs';

    private $docs = null;

    private $models = [];
    
    public function handle()
    {
        $url = 'http://feed.intra.sit.ffan.com/v2/api-docs';
        $url = 'http://10.209.232.169:11331/v2/api-docs';
        $docs = file_get_contents($url);
        $docs = json_decode($docs);

        $this->docs = $docs;

        $actions = [];
        foreach ($docs->paths as $path => $methods) {
            foreach ($methods as $method => $info) {
                $action = sprintf($this->actionTemplate(), $info->summary);

                $request = sprintf($this->requestTemplate(), $path, strtoupper($method));

                if (isset($info->parameters)) {
                    $requestParams = $this->formatParameters($info->parameters);
                } else {
                    $requestParams = [];
                }

                $request = str_replace('{parameters}', implode("\n", $requestParams), $request);

                $response = $this->responseTemplate();
                $responseParams = $this->formatParameter($info->responses->{'200'});
                $response = str_replace('{parameters}', $responseParams, $response);

                $action = str_replace('{request}', $request, $action);
                $action = str_replace('{response}', $response, $action);

                $actions[] = $action;
            }
        }

        $modelStr = implode("\n", $this->models);
        $actionStr = implode("\n", $actions);

        echo <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<protocol>
    {$modelStr}
    {$actionStr}
</protocol>

XML;
    }

    private function formatParameters(array $parameters)
    {
        $params = [];
        foreach ($parameters as $parameter) {
            $params[] = $this->formatParameter($parameter);
        }

        return $params;
    }

    private function formatParameter($parameter)
    {
        if (isset($parameter->type) && $parameter->type === 'array') {
            $name = isset($parameter->name) ? $parameter->name : '';
            $description = isset($parameter->description) ? $parameter->description : '';
            $theList = sprintf('<list name="%s" note="%s">{items}</list>', $name, $description);

            $items = $this->formatParameter($parameter->items);

            return str_replace('{items}', $items, $theList);
        } elseif (isset($parameter->type)) {
            switch ($parameter->type) {
                case 'integer':
                    $type = 'int';
                    break;
                case 'string':
                    $type = 'string';
                    break;
                case 'number':
                    $type = 'float';
                    break;
                case 'array':
                    $type = 'list';
                    break;
                case 'object':
                    $type = 'model';
                    break;

                default:
                    $type = $parameter->type;
            }

            $name = isset($parameter->name) ? $parameter->name : '';
            $description = isset($parameter->description) ? $parameter->description : '';

            return sprintf($this->paramTemplate(), $type, $name, $description, $type);
        } else {
            // 对象类型
            /*
            if (!empty($parameter->name) && isset($this->models[$parameter->name])) {
                return sprintf('<model name="%s" extend="%s"></model>', $parameter->name, $parameter->name);
            }
            */

            $name = isset($parameter->name) ? $parameter->name : '{modelName}';
            $description = isset($parameter->description) ? $parameter->description : '';
            $model = sprintf('<model name="%s" note="%s">{params}</model>', $name, $description);

            $params = $this->resolveRef($this->getRef($parameter));
            $params = $this->formatParameters($params);

            $model = str_replace('{params}', implode("\n", $params), $model);

            /*
            if (!empty($parameter->name)) {
                $this->models[$parameter->name] = $model;

                return sprintf('<model name="%s" extend="%s"></model>', $parameter->name, $parameter->name);
            }
            */
            // print_r($model);die;

            return $model;
        }
    }

    private function resolveRef($ref)
    {
        if (empty($ref)) {
            return [];
        }

        $value = [];

        $ref = trim($ref, '#/');
        // print_r($ref);die;
        $ref = explode('/', $ref);

        if (!empty($ref) && is_array($ref)) {
            $key = array_shift($ref);

            if (isset($this->docs->$key)) {
                $value = $this->docs->$key;
            }

            foreach ($ref as $item) {
                if (isset($value->$item)) {
                    $value = $value->$item;
                }
            }
        }

        $properties = [];
        if (isset($value->properties)) {
            foreach ($value->properties as $name => $property) {
                $property->name = $name;
                $properties[] = $property;
            }
        }

        return $properties;
    }

    /**
     * ref 会有多种情况 统一处理
     * @param $parameter
     * @return mixed
     * @throws \Exception
     */
    private function getRef($parameter)
    {
        if (isset($parameter->schema->{'$ref'})) {
            return $parameter->schema->{'$ref'};
        } elseif (isset($parameter->{'$ref'})) {
            return $parameter->{'$ref'};
        }

        print_r($parameter);
        // throw new \Exception('发现一个无法处理的 ref');
    }

    private function actionTemplate()
    {
        $t = <<<TEMPLATE
<action name="{actionName}" note="%s">
{request}
{response}
</action>

TEMPLATE;
        return $t;
    }

    private function requestTemplate()
    {
        $t = <<<TEMPLATE
    <request uri="%s" method="%s">
        {parameters}
    </request>
TEMPLATE;

        return $t;
    }

    private function responseTemplate()
    {
        $t = <<<TEMPLATE
    <response extend="/api/result">
        {parameters}
    </response>
TEMPLATE;

        return $t;
    }

    private function paramTemplate()
    {
        return '<%s name="%s" note="%s"></%s>';
    }
}

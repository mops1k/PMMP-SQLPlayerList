<?php
namespace {

    class PluginConfig
    {
        public static function getConfiguration()
        {
            return [
                'namespace' => 'mops1k\SQLPlayerList',
                'commands'  => [],
                'listeners' => [
                    'player'
                ],
            ];
        }
    }
}

<?php

namespace Shared\Infrastructure\Messenger;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class MessengerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $busIds = array_keys($container->findTaggedServiceIds('messenger.bus'));

        // register an app.messenger.handle_saga for each bus with its proper handlers locator
        foreach ($busIds as $bus) {
            if (!$container->has($locatorId = $bus.'.messenger.handlers_locator')) {
                continue;
            }
            if ($container->has($handleMessageId = $bus.'.middleware.app.messenger.handle_saga')) {
                $container->getDefinition($handleMessageId)
                    ->replaceArgument(0, new Reference($locatorId));
            }
        }
    }
}

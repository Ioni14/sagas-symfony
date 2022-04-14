
https://docs.particular.net/tutorials/nservicebus-sagas/

On peut penser à un Saga comme des règles à suivre (policies) car l'utilisation principale est de décider quoi faire à chaque nouveau message.

Par convention on va utiliser le suffixe Policy pour nos Sagas. Exemple: ShippingPolicy qui requiert que la commande soit à la fois "Placed" et "Billed" avant de l'envoyer.

    composer install

    docker-compose up -d

    bin/console doctrine:database:create
    bin/console doctrine:migrations:migrate -n
    bin/console doctrine:query:sql "insert into shipping_policy_state(id,correlation_order_id,state) values (UuidToBin('00000000-0000-0000-0000-000000000001'), UuidToBin('00000000-0000-0000-0000-000000000001'), '{\"order_placed\":true,\"order_billed\":false}');"

    bin/console messenger:consume sales_commands -vv
    bin/console messenger:consume sales_events -vv
    bin/console messenger:consume billing_events -vv
    bin/console messenger:consume shipping_commands -vv
    bin/console app:place-order 00000000-0000-0000-0000-000000000001 -vv
    # Cancel within 5 seconds... or not.
    bin/console app:cancel-order 00000000-0000-0000-0000-000000000001 -vv

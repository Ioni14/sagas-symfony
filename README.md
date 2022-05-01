
https://docs.particular.net/tutorials/nservicebus-sagas/

On peut penser à un Saga comme des règles à suivre (policies) car l'utilisation principale est de décider quoi faire à chaque nouveau message.

Par convention on va utiliser le suffixe Policy pour nos Sagas "Observer". Exemple: ShippingPolicy qui requiert que la commande soit à la fois "Placed" et "Billed" avant de l'envoyer. Et le suffixe Workflow pour nos Sagas "Commander".

    make up

    bin/console messenger:consume sales_commands -vv
    bin/console messenger:consume sales_events -vv
    bin/console messenger:consume billing_events -vv
    bin/console messenger:consume shipping_commands -vv
    bin/console messenger:consume shipping_events -vv
    bin/console app:place-order 00000000-0000-0000-0000-000000000001 -vv
    # Cancel within 5 seconds... or not.
    bin/console app:cancel-order 00000000-0000-0000-0000-000000000001 -vv

### TODO :

* Specific logger channel
* How to handle ghost state in persistance (states persisted forever) ? make a state cleaner ?
* SagaPersister implementations : other SQL dialects, Redis ...
* Reply To Sender (for async request/response messages)
* Monolog processor pour les métadonnées des sagas
* SF Profiler integration (i.e. DataCollector)
* Dashboard for monitoring (débits, messages en attente, temps de traitement...)

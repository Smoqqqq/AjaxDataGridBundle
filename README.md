# DataGrid Bundle
A bundle to display data in a grid with pagination, sorting and filtering capabilities.

## Installation


Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

Open a command console, enter your project directory and execute:

```console
composer require smoq/datagrid-bundle
```

#### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    Smoq\DataGridBundle\AjaxDataGridBundle::class => ['all' => true],
];
```

### Including Javascript and CSS
For the moment, a single Javascript file is required, and needs to be imported with webpack :

```javascript
Encore
    .addEntry('datagrid', './vendor/smoq/datagrid-bundle/assets/js/datagrid.js')
```

this file requires a CSS file, which you can then include in your template (along with the JS file) :

```twig
{% block stylesheets %}
    {{ parent() }}
    {{ encore_entry_link_tags('datagrid') }}
{% endblock %}

{% block javascripts %}
    {{ parent() }}
    {{ encore_entry_script_tags('datagrid') }}
{% endblock %}
```

## Basic usage

Create a DataGrid like so :

```php
<?php

namespace App\DataGrid;

use Smoq\DataGridBundle\AjaxDataGrid;

class StationDataGrid extends AjaxDataGrid
{
    public function configure(): void
    {
        $this->query(   // Define the query to fetch data
            $this->createQueryBuilder()
                ->from(Station::class, 's')
                ->select('s')
                ->orderBy('s.name', 'ASC')
        )
            ->filter('name', TextType::class, [    // Filter on a field
                'label' => 'Name',
                'required' => false,
            ], function (QueryBuilder $qb, $value) {
                return $qb->andWhere('s.name LIKE :name')
                    ->setParameter('name', '%' . $value . '%');
            })
            ->filter('massif', ChoiceType::class, [
                'label' => 'Massif',
                'required' => false,
                'choices' => [
                    'Alpes' => 'Alpes',
                    'Pyrénées' => 'Pyrénées',
                    'Jura' => 'Jura',
                    'Vosges' => 'Vosges',
                    'Massif central' => 'Massif central',
                    'Corse' => 'Corse',
                    'Autres' => 'Autres',
                ]
            ], function (QueryBuilder $qb, $value) {
                return $qb->andWhere('s.massif = :massif')
                    ->setParameter('massif', $value);
            })
            ->setDisplayFields([    // Set fields to display
                'Id' => 'id',
                'Nom' => 'name',
                'Date de modification' => 'updatedAt',
                'Massif' => 'massif',
                'Nombre de pistes' => function (Station $station) {
                    return $station->getNbPistesBleues() + $station->getNbPistesRouges() + $station->getNbPistesVertes();
                }
            ])
            ->removeDisplayField('Id')    // Remove a field from the display
            ->addActionLink('Supprimer', function (RouterInterface $router, Station $station) {    // Add an action (on each line)
                return $router->generate('app_back_station_delete', ['id' => $station->getId()]);
            })
            ->addActionLink('Editer', function (RouterInterface $router, Station $station) {
                return $router->generate('app_back_station_update', ['id' => $station->getId()]);
            })
            ->setMaxResults(5);    // Set nb of items per page
    }
}
```

Then, in your controller define two routes :
- a route to display the datagrid
```php
#[Route('/station-datagrid', name: 'app_station')]
public function datagrid(StationDataGrid $dataGrid): Response
{
    return $this->render('datagrid/station.html.twig', [
        'datagrid' => $dataGrid->execute()
    ]);
}
```

- a route to handle the AJAX requests
```php
#[Route('/station-datagrid/ajax', name: 'app_station_ajax')]
public function datagridAjax(StationDataGrid $dataGrid): JsonResponse
{
    return $this->json($dataGrid->ajax());
}
```

A sample response of the Ajax endpoint :

```json
{
    "items": [
        {
            "data": [
                "Aurore Kuhlman IV1038",
                "21/05/2024",
                "Alpes",
                "0"
            ],
            "actions": [
                {
                    "url": "/back-office/station/25/supprimer",
                    "label": "Supprimer"
                },
                {
                    "url": "/back-office/station/25/modifier",
                    "label": "Editer"
                }
            ]
        }
    ],
    "nbPages": 2,
    "currentPage": 1
}
```
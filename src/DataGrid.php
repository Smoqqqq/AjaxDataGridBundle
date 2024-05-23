<?php

namespace Smoq\DataGridBundle;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\ItemInterface;

abstract class DataGrid
{
    public const string DEFAULT_METHOD = 'GET';
    public const int DEFAULT_MAX_RESULTS = 20;

    private readonly FilesystemAdapter $cache;

    protected string $select;

    protected QueryBuilder $qb;

    protected array $filters = [];

    protected array $displayFields = [];

    protected FormBuilderInterface $formBuilder;

    private FormInterface $filterForm;

    private Request $request;

    protected string $method = self::DEFAULT_METHOD;

    protected int $maxResults = self::DEFAULT_MAX_RESULTS;

    private string $dateFormat = 'd/m/Y';

    private array $actionLinks = [];

    public int $currentPage = 1;

    public function __construct(private readonly EntityManagerInterface $em, private readonly RouterInterface $router, FormFactoryInterface $formFactory)
    {
        $this->cache = new FilesystemAdapter();

        $this->formBuilder = $formFactory->createNamedBuilder($this->getId(), options: [
            'method' => $this->method,
            'block_prefix' => $this->getId(),
            'attr' => [
                'id' => $this->getId() . '-datagrid-filter-form'
            ]
        ]);
        $this->request = Request::createFromGlobals();
    }

    private function addPagination() {
        $this->filterForm->add('_page', HiddenType::class, [
            'data' => $this->currentPage
        ]);
    }

    /**
     * needs to be executed before passing the datagrid to the template
     */
    public function execute(): self
    {
        $this->formBuilder->setMethod(self::DEFAULT_METHOD);
        $this->configure();

        $this->filterForm = $this->formBuilder->getForm();
        $this->addPagination();
        $this->filterForm->handleRequest($this->request);

        if ($this->filterForm->isSubmitted() && $this->filterForm->isValid()) {
            $this->addOrdering();
            $this->applyFilters();
        }

        return $this;
    }

    /**
     * needs to be executed before passing the datagrid to the template
     */
    public function ajax(): array
    {
        $items = $this->execute()->getResults();
        $data = [
            'items' => [],
            'nbPages' => $this->getNbPages(),
            'currentPage' => $this->getCurrentPage()
        ];
        $i = 0;

        foreach ($items as $item) {
            $data['items'][$i] = [
                'data' => [],
                'actions' => [],
            ];

            foreach ($this->getDisplayFields() as $field) {
                $data['items'][$i]['data'][] = $this->displayField($field, $item);
            }

            foreach ($this->getActionLinks() as $link) {
                $data['items'][$i]['actions'][] = [
                    'url' => $this->generateUrl($link, $item),
                    'label' => $link['label']
                ];
            }

            ++$i;
        }

        return $data;
    }

    /**
     * method to implement in the child class to configure the DataGrid
     */
    public function configure(): void
    {
        throw new \LogicException('You must implement the configure method yourself.');
    }

    /**
     * whether to use POST or GET method. Defaults to GET
     */
    public function setMethod(string $method): self
    {
        $this->formBuilder->setMethod($method);
        return $this;
    }

    /**
     * Create a query builder
     * @return QueryBuilder
     */
    public function createQueryBuilder(): QueryBuilder
    {
        $this->qb = $this->em->createQueryBuilder();

        return $this->qb;
    }

    /**
     * get the query results
     */
    public function getResults(): Paginator
    {
        // Must have a non-null key, so add an arbitrary string in front
        $key = 'request_'.$this->request->getQueryString();

        $this->cache->delete($key);

        // return the item from cache, and set it if it doesn't exist
        return $this->cache->get($key, function (ItemInterface $item) {
            $item->expiresAfter(3600);

            $this->qb->setFirstResult(($this->currentPage - 1) * $this->maxResults)
                ->setMaxResults($this->maxResults);

            return new Paginator($this->qb, true);
        });
    }

    /**
     * Initial query to get the results
     * @param QueryBuilder $qb
     * @return $this
     */
    public function query(QueryBuilder $qb): self
    {
        $this->qb = $qb;

        return $this;
    }

    public function setMaxResults(int $nbResults): self
    {
        $this->maxResults = $nbResults;

        return $this;
    }

    /**
     * Add a filter
     * @param string $name just like in a classic form type
     * @param class-string $type form type class (eg: TextType::class)
     * @param array $options array of options just like in a class Symfony form
     * @param callable $callback callable applying the filter to the query builder. Will be passed two parameters : QueryBuilder $qb, mixed $value
     * @return $this
     */
    public function filter(string $name, string $type, array $options, callable $callback): self
    {
        $options['attr']['id'] = $this->getId() . '-datagrid-filter-' . $name;

        $this->formBuilder->add($name, $type, $options);

        $this->filters[$name] = $callback;

        return $this;
    }

    /**
     * @internal
     * apply filters to the query builder
     */
    private function applyFilters(): void
    {
        $this->currentPage = $this->request->query->all()['form']['_page'] ?? 1;

        foreach ($this->filters as $key => $filter) {
            $qb = call_user_func($filter, $this->qb, $this->filterForm->get($key)->getData());

            $this->qb = $qb;
        }
    }

    /**
     * Set fields to display in the UI
     * @param array $fields key-value pairs, with the key being the title in the header, the value being the column name or a callable
     * @return $this
     */
    public function setDisplayFields(array $fields): self
    {
        $this->displayFields = $fields;

        return $this;
    }

    /**
     * Add a field to display in the UI
     * @param array $fields key-value pairs, with the key being the title in the header, the value being the column name
     * @return $this
     */
    public function addDisplayField(string $title, string|callable $field): self
    {
        $this->displayFields[$title] = $field;

        return $this;
    }

    /**
     * Remove a field that was previously set to be displayed in the UI
     * @param string $title
     * @return $this
     */
    public function removeDisplayField(string $title): self
    {
        unset($this->displayFields[$title]);

        return $this;
    }

    /**
     * @internal
     * get the fields to display in the template
     */
    public function getDisplayFields(): array
    {
        return $this->displayFields;
    }

    /**
     * get the form view to be displayed in the template
     */
    public function getFilterFormView(): FormView
    {
        return $this->filterForm->createView();
    }

    /**
     * @param string $dateFormat - DateTime compatible format to be used to display dates - defaults to 'd/m/Y'
     * @return $this
     */
    public function setDateFormat(string $dateFormat): self
    {
        $this->dateFormat = $dateFormat;

        return $this;
    }

    /**
     * @internal
     */
    public function getDateFormat(): string
    {
        return $this->dateFormat;
    }

    /**
     * @internal
     * used to know if there are action links to be included in the template
     */
    public function hasActions(): bool
    {
        return count($this->actionLinks) > 0;
    }

    /**
     * Add an action link to the grid
     * @param string $label Text content of the button. Can contain HTML
     * @param callable $urlGeneratingCallback a callable that will generate the URL of the link. Will be passed two parameters : RouterInterface $router, object $object
     * @return $this
     */
    public function addActionLink(string $label, callable $urlGeneratingCallback): self
    {
        $this->actionLinks[] = [
            'label' => $label,
            'callback' => $urlGeneratingCallback
        ];

        return $this;
    }

    /**
     * @internal
     * used to get all action links in the template
     */
    final public function getActionLinks(): array
    {
        return $this->actionLinks;
    }

    /**
     * @internal
     * used to generate the URL of an action link in the template
     */
    final public function generateUrl(array $link, object $object) {
        return call_user_func($link['callback'], $this->router, $object);
    }

    /**
     * @internal
     * used to generate the text content of a field
     */
    final public function displayField(string|callable $field, object $object): string
    {
        if (is_string($field)) {
            if (!method_exists($object, 'get' . ucfirst($field))) {
                throw new \LogicException('No getter found for field ' . $field . ' in ' . get_class($object) . ' (tried ' . 'get' . ucfirst($field) . ')');
            }

            $data = $object->{'get' . ucfirst($field)}();

            if ($data instanceof \DateTimeInterface) {
                return $data->format($this->dateFormat);
            }

            return $data;
        }

        return call_user_func($field, $object);
    }

    public function getNbPages(): int
    {
        return max(ceil(count($this->getResults()) / $this->maxResults), 1);
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /*
     * @internal
     * order the results according to the ordering set in the request
     */
    public function addOrdering() {
        $ordering = $this->request->query->all()['_datagrid_ordering'] ?? [];

        foreach ($ordering as $item) {
            $this->qb->addOrderBy($item['field'], $item['direction']);
        }
    }

    public function getId() {
        return str_replace('\\', '_', static::class);
    }
}
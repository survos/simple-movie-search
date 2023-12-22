<?php

namespace App\EventListener;

use Survos\BootstrapBundle\Event\KnpMenuEvent;
use Survos\BootstrapBundle\Traits\KnpMenuHelperInterface;
use Survos\BootstrapBundle\Traits\KnpMenuHelperTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[AsEventListener(event: KnpMenuEvent::NAVBAR_MENU, method: 'navbarMenu')]
// #[AsEventListener(event: KnpMenuEvent::NAVBAR_MENU2, method: 'navbarMenu')]
#[AsEventListener(event: KnpMenuEvent::SIDEBAR_MENU, method: 'sidebarMenu')]
#[AsEventListener(event: KnpMenuEvent::PAGE_MENU, method: 'pageMenu')]
#[AsEventListener(event: KnpMenuEvent::FOOTER_MENU, method: 'footerMenu')]
final class AppMenuEventListener implements KnpMenuHelperInterface
{
    use KnpMenuHelperTrait;

    public function __construct(
#[Autowire('%kernel.environment%')] private string $env,
private Security $security,
private ?AuthorizationCheckerInterface $authorizationChecker = null
) {
    }

    public function navbarMenu(KnpMenuEvent $event): void
    {
        $menu = $event->getMenu();
        $options = $event->getOptions();

//        $this->add($menu, 'app_homepage');
        // for nested menus, don't add a route, just a label, then use it for the argument to addMenuItem

        $nestedMenu = $this->addSubmenu($menu, 'Browse');
        $this->add($nestedMenu, 'app_browse', label: 'doctrine');
        $this->add($nestedMenu, 'app_browse_dynamic', label: 'meili');



//        'app_browse': "Browse using APIPlatform with doctrine/postgres",
//                'app_browse_meili': "Browse using API Platform / Meilisearch",
//                'app_browse_dynamic': "Browse using Meilisearch without class",
//                'imdb_test_cache': "Test the CSV Cache Adapter",
//                'imdb_import': "Import using CSV Database",
//                'import_with_parser': "Import using Parser",
//                'test_parser': "Test Parser (@todo: create phpunit test in grid-group)",
//                'survos_commands': "Run bin/console",
//



        foreach (['bundles', 'javascript'] as $type) {
            // $this->addMenuItem($nestedMenu, ['route' => 'survos_base_credits', 'rp' => ['type' => $type], 'label' => ucfirst($type)]);
            $this->addMenuItem($nestedMenu, ['uri' => "#$type", 'label' => ucfirst($type)]);
        }
    }

    public function sidebarMenu(KnpMenuEvent $event): void
    {
        $menu = $event->getMenu();
        $options = $event->getOptions();
    }

    public function footerMenu(KnpMenuEvent $event): void
    {
        $menu = $event->getMenu();
        $options = $event->getOptions();
        $this->add($menu, uri: 'https://github.com');
    }

    // this could also be called the content menu, as it's below the navbar, e.g. a menu for an entity, like show, edit
    public function pageMenu(KnpMenuEvent $event): void
    {
        $menu = $event->getMenu();
        $options = $event->getOptions();
    }
}

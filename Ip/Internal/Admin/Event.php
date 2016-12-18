<?php


namespace Ip\Internal\Admin;


class Event
{
    protected static function getAdminNavbarHtml()
    {
        $requestData = \Ip\ServiceLocator::request()->getRequest();
        $curModTitle = '';
        $curModUrl = '';
        $curModIcon = '';

        if (!empty($requestData['aa'])) {
            $parts = explode('.', $requestData['aa']);
            $curModule = $parts[0];
        } else {
            $curModule = "Content";
        }

        if (isset($curModule) && $curModule) {
            $title = $curModule;
            $plugin = \Ip\Internal\Plugins\Service::getPluginConfig($curModule);
            if ($plugin) {
                $title = $plugin['title'];
            }
            $curModTitle = __($title, 'Ip-admin', false);
            $curModUrl = ipActionUrl(array('aa' => $curModule . '.index'));
            $curModIcon = Model::getAdminMenuItemIcon($curModule);
        }

        $navbarButtons = array(
            array(
                'text' => '',
                'hint' => __('Logout', 'Ip-admin', false),
                'url' => ipActionUrl(array('sa' => 'Admin.logout')),
                'class' => 'ipsAdminLogout',
                'faIcon' => 'fa-power-off'
            )
        );

        $navbarButtons = ipFilter('ipAdminNavbarButtons', $navbarButtons);

        $navbarCenterElements = ipFilter('ipAdminNavbarCenterElements', []);

        $data = array(
            'menuItems' => Model::instance()->getAdminMenuItems($curModule),
            'curModTitle' => $curModTitle,
            'curModUrl' => $curModUrl,
            'curModIcon' => $curModIcon,
            'navbarButtons' => array_reverse($navbarButtons),
            'navbarCenterElements' => $navbarCenterElements
        );


        $html = ipView('view/navbar.php', $data)->render();
        return $html;
    }

    public static function ipInitFinished ()
    {
        $request = \Ip\ServiceLocator::request();
        $safeMode = $request->getQuery('safeMode');
        if ($safeMode === null) {
            $safeMode = $request->getQuery('safemode');
        }

        if ($safeMode !== null && \Ip\Internal\Admin\Backend::userId()) {
            Model::setSafeMode($safeMode);
        }
    }

    public static function ipBeforeController()
    {


        //show admin submenu if needed
        if (ipRoute()->isAdmin()) {
            ipAddJs('Ip/Internal/Core/assets/js/jquery-ui/jquery-ui.js');
            ipAddCss('Ip/Internal/Core/assets/js/jquery-ui/jquery-ui.css');

            $submenu = Submenu::getSubmenuItems();
            $submenu = ipFilter('ipAdminSubmenu', $submenu);
            if ($submenu) {
                ipResponse()->setLayoutVariable('submenu', $submenu);
            }
        }

        // Show admin toolbar if admin is logged in:
        if (ipAdminId() && !ipRequest()->getRequest('pa') || ipRequest()->getRequest('aa') && ipAdminId()) {
            if (!ipRequest()->getQuery('ipDesignPreview') && !ipRequest()->getQuery('disableAdminNavbar')) {
                ipAddJs('Ip/Internal/Admin/assets/admin.js');
                ipAddJsVariable('ipAdminNavbar', static::getAdminNavbarHtml());
            }
        }

        // Show popup with autogenerated user information if needed
        $adminIsAutogenerated = ipStorage()->get('Ip', 'adminIsAutogenerated');
        if ($adminIsAutogenerated) {
            $adminId = \Ip\Internal\Admin\Backend::userId();
            $admin = \Ip\Internal\Administrators\Model::getById($adminId);
            ipAddJs('Ip/Internal/Admin/assets/adminIsAutogenerated.js');
            $data = array(
                'adminUsername' => $admin['username'],
                'adminPassword' => ipStorage()->get('Ip', 'adminIsAutogenerated'),
                'adminEmail' => $admin['email']
            );
            ipAddJsVariable('ipAdminIsAutogenerated', ipView('view/adminIsAutoGenerated.php', $data)->render());
        }


        if (ipContent()->getCurrentPage()) {
            // initialize management
            if (ipIsManagementState()) {
                if (!ipRequest()->getQuery('ipDesignPreview') && !ipRequest()->getQuery('disableManagement')) {
                    \Ip\Internal\Content\Helper::initManagement();
                }
            }

            //show page content
            $response = ipResponse();
            $response->setDescription(\Ip\ServiceLocator::content()->getDescription());
            $response->setKeywords(ipContent()->getKeywords());
            $response->setTitle(ipContent()->getTitle());

        }


    }

    public static function ipAdminLoginFailed($data)
    {
        $securityModel = SecurityModel::instance();
        $securityModel->registerFailedLogin($data['username'], $data['ip']);
    }

    public static function ipCronExecute($data)
    {
        if ($data['firstTimeThisDay']) {
            $securityModel = SecurityModel::instance();
            $securityModel->cleanup();
        }
    }

}

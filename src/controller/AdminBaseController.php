<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2019 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +---------------------------------------------------------------------
// | Author: 小夏 < 449134904@qq.com>
// +----------------------------------------------------------------------
namespace cmf\controller;

use cmf\model\UserModel;
use cmf\model\UserTokenModel;
use think\Db;

class AdminBaseController extends BaseController
{

    protected function initialize()
    {
        // 监听admin_init----------------
        hook('admin_init');
        parent::initialize();
        
        $siteInfo = cmf_get_site_info();
        $this->assign("configpub", $siteInfo);
        
        /* redis缓存开启 */
        connectionRedis();
        
        $sessionAdminId = session('ADMIN_ID');
        if (!empty($sessionAdminId)) {
            $user = UserModel::where('id', $sessionAdminId)->find();

            //判断session是否一致
            $last_session = $user['session'];//最后一次登录session
            $user_session = session("session");//当前用户session

            if(!empty($user_session) &&  $user_session != $last_session){
                $this->error("账号在其它地方登录，你被踢出！", url("admin/public/logout"));
            }

            if(config("google_auth")){
                $sessionGoogleAuthStatus = session('google_auth_session');
                if (!$sessionGoogleAuthStatus) {
                    $this->success("谷歌身份二次验证！", url("admin/public/google"));
                }
            }

            if (!$this->checkAccess($sessionAdminId)) {
                $this->error("您没有访问权限！");
            }
            $this->assign("admin", $user);
        } else {
            if ($this->request->isPost()) {
                $this->error("您还没有登录！", url("admin/public/login"));
            } else {
                return $this->redirect(url("admin/Public/login"));
            }
        }
    }

    public function _initializeView()
    {
        $cmfAdminThemePath    = config('template.cmf_admin_theme_path');
        $cmfAdminDefaultTheme = cmf_get_current_admin_theme();

        $themePath = "{$cmfAdminThemePath}{$cmfAdminDefaultTheme}";

        $root = cmf_get_root();

        //使cdn设置生效
        $cdnSettings = cmf_get_option('cdn_settings');
        if (empty($cdnSettings['cdn_static_root'])) {
            $viewReplaceStr = [
                '__ROOT__'     => $root,
                '__TMPL__'     => "{$root}/{$themePath}",
                '__STATIC__'   => "{$root}/static",
                '__WEB_ROOT__' => $root
            ];
        } else {
            $cdnStaticRoot  = rtrim($cdnSettings['cdn_static_root'], '/');
            $viewReplaceStr = [
                '__ROOT__'     => $root,
                '__TMPL__'     => "{$cdnStaticRoot}/{$themePath}",
                '__STATIC__'   => "{$cdnStaticRoot}/static",
                '__WEB_ROOT__' => $cdnStaticRoot
            ];
        }

        config('template.view_base', CMF_ROOT . "$themePath/");
        config('template.tpl_replace_string', $viewReplaceStr);
    }

    /**
     * 初始化后台菜单
     */
    public function initMenu()
    {
    }

    /**
     *  检查后台用户访问权限
     * @param int $userId 后台用户id
     * @return boolean 检查通过返回true
     */
    private function checkAccess($userId)
    {
        // 如果用户id是1，则无需判断
        if ($userId == 1) {
            return true;
        }

        $module     = $this->request->module();
        $controller = $this->request->controller();
        $action     = $this->request->action();
        $rule       = $module . $controller . $action;

        $notRequire = ["adminIndexindex", "adminMainindex"];
        if (!in_array($rule, $notRequire)) {
            return cmf_auth_check($userId);
        } else {
            return true;
        }
    }

}

<?php

namespace pkpudev\simplesaml;

use Yii;
use yii\base\InvalidConfigException;
use yii\web\User;

class WebUser extends User
{
    public $autoloaderPath;
    public $authSource;
    public $attributesConfig=[];

    public $superuserCheck = false;
    public $superuserPermissionName = 'superuserAccess';

    private $_saml;
    private $_attributes;

    public function getSaml()
    {
        if (!isset($this->_saml)) {

            if (!isset($this->autoloaderPath))
                throw new InvalidConfigException("SimpleSAMLPHP's autoloader file is not set");
            if (!file_exists($this->autoloaderPath))
                throw new InvalidConfigException("SimpleSAMLPHP's autoloader file doesn't exists");
            if (!isset($this->authSource))
                throw new InvalidConfigException("SimpleSAMLPHP's auth source is not set");
            
            require_once($this->autoloaderPath);

            $this->_saml = new \SimpleSAML_Auth_Simple($this->authSource);

            $this->checkId();
        }

        return $this->_saml;
    }

    protected function getAttributesInternal()
    {
        $saml = $this->getSaml();

        $attributes = array();
        $attributesConfig = $this->attributesConfig;
        $coreAttributes = $saml->getAttributes();
        
        foreach ($attributesConfig as $localAttr => $coreAttr)
            if (isset($coreAttributes[$coreAttr][0]))
                $attributes[$localAttr] = $coreAttributes[$coreAttr][0];

        return $attributes;
    }

    protected function setAttributes($attributes=array())
    {
        if (empty($attributes))
            $this->_attributes = $this->getAttributesInternal();
        else
            $this->_attributes = $attributes;
    }

    public function getAttributes()
    {
        if (!isset($this->_attributes))
            $this->setAttributes();

        return $this->_attributes;
    }

    public function getAttribute($name)
    {
        if (!isset($this->_attributes))
            $this->setAttributes();

        if (isset($this->_attributes[$name]))
            return $this->_attributes[$name];
        else
            return null;
    }

    public function hasAttribute($name)
    {
        if (!isset($this->_attributes))
            $this->setAttributes();

        if (isset($this->_attributes[$name]))
            return true;
        else
            return false;
    }

    public function getIdentity($autoRenew = true)
    {
        if ($this->enableSession) {
            $this->checkId();
        } 

        return parent::getIdentity($autoRenew);
    }

    public function getIsGuest()
    {
        if ($this->enableSession) {
            return !$this->getSaml()->isAuthenticated();
        }
        return $this->getIdentity() === null;
        
    }

    public function getId()
    {
        return $this->getAttribute('id');
    }

    /**
     * @var string the class name of the [[identity]] object.
     */
    public $identityClass = 'pkpudev\simplesaml\UserIdentity';

    /**
     * Logs out the current user.
     * This will remove authentication-related session data.
     * If `$destroySession` is true, all session data will be removed.
     * @param boolean $destroySession whether to destroy the whole session. Defaults to true.
     * This parameter is ignored if [[enableSession]] is false.
     * @return boolean whether the user is logged out
     */
    public function logout($destroySession = true)
    {
        if ($destroySession && $this->enableSession) {
            Yii::$app->getSession()->destroy();
        }
        if ($this->getSaml()->isAuthenticated()) {
            $request = Yii::$app->getRequest();
            $id = $this->getId();
            $ip = $request->getUserIP();
            Yii::info("User '$id' logging out from $ip by SimpleSAMLPHP.", __METHOD__);
            $this->getSaml()->logout([
                'ReturnTo' => $request->getUrl(),
            ]);
        }

        return $this->getIsGuest();
    }

    public function loginRequired($checkAjax = true, $checkAcceptHeader = true)
    {
        $request = Yii::$app->getRequest();
        if ($this->enableSession && (!$checkAjax || !$request->getIsAjax())) {
            $this->setReturnUrl($request->getUrl());
        }
        if (!$this->getSaml()->isAuthenticated()) {
            if ($checkAjax && $request->getIsAjax()) {
                return Yii::$app->getResponse()->redirect($this->getReturnUrl());
            } else {
                $this->getSaml()->login([
                    'ReturnTo' => $request->getUrl(),
                    'KeepPost' => true,
                ]);
            }
        }
        $this->checkId(false);
    }

    protected function checkId($loginRequired = true, $checkAjax = true)
    {
        $id = $this->getId();
        $session = Yii::$app->getSession();
        if ($id !== null) {
            if ($session->has($this->idParam)) {
                if ($session->get($this->idParam)!==$id)
                    $session->set($this->idParam, $id);
            } else
                $session->set($this->idParam, $id);
        } elseif ($session->has($this->idParam) && $loginRequired) {
            $this->loginRequired($checkAjax);
        }
    }

    public function can($permissionName, $params = [], $allowCaching = true)
    {
        if ($this->superuserCheck && parent::can($this->superuserPermissionName, [], true))
            return true;

        return parent::can($permissionName, $params, $allowCaching);
    }
}

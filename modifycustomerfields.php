<?php

/**
 * 2007-2024 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2024 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class ModifyCustomerFields extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'modifycustomerfields';
        $this->tab = 'administration';
        $this->version = '1.1.0'; // Incrementa la versión
        $this->author = 'Josep Pino';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Modify Customer Fields');
        $this->description = $this->l('Permite modificar manualmente el tamaño de los campos de nombre y apellidos en el formulario de registro de cliente.');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('actionCustomerAccountAdd') &&
            $this->registerHook('header') &&
            $this->registerHook('displayBackOfficeHeader');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitModifyCustomerFieldsModule')) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);
        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl') . $this->renderForm();
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitModifyCustomerFieldsModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'name' => 'MODIFYCUSTOMERFIELDS_FIRSTNAME_LENGTH',
                        'label' => $this->l('Límite de caracteres para el nombre'),
                        'desc' => $this->l('Introduce el límite de caracteres permitido para el nombre (max 512).'),
                        'required' => true,
                        'max' => 512,
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'MODIFYCUSTOMERFIELDS_LASTNAME_LENGTH',
                        'label' => $this->l('Límite de caracteres para los apellidos'),
                        'desc' => $this->l('Introduce el límite de caracteres permitido para los apellidos (max 512).'),
                        'required' => true,
                        'max' => 512,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Guardar'),
                ),
            ),
        );
    }

    protected function getConfigFormValues()
    {
        return array(
            'MODIFYCUSTOMERFIELDS_FIRSTNAME_LENGTH' => Configuration::get('MODIFYCUSTOMERFIELDS_FIRSTNAME_LENGTH', 512),
            'MODIFYCUSTOMERFIELDS_LASTNAME_LENGTH' => Configuration::get('MODIFYCUSTOMERFIELDS_LASTNAME_LENGTH', 512),
        );
    }

    protected function postProcess()
    {
        $firstnameLength = (int)Tools::getValue('MODIFYCUSTOMERFIELDS_FIRSTNAME_LENGTH');
        $lastnameLength = (int)Tools::getValue('MODIFYCUSTOMERFIELDS_LASTNAME_LENGTH');

        // Validar que los valores estén en el rango permitido
        if ($firstnameLength > 0 && $firstnameLength <= 512 && $lastnameLength > 0 && $lastnameLength <= 512) {
            // Modifica el tamaño de las columnas en la base de datos
            $sql = 'ALTER TABLE ' . _DB_PREFIX_ . 'customer 
                    MODIFY firstname VARCHAR(' . $firstnameLength . '),
                    MODIFY lastname VARCHAR(' . $lastnameLength . ');';

            if (!Db::getInstance()->execute($sql)) {
                throw new PrestaShopException('Error al actualizar el tamaño de los campos en la base de datos.');
            }
        } else {
            throw new PrestaShopException('Los valores deben ser mayores que 0 y menores o iguales a 512.');
        }
    }

    public function hookActionCustomerAccountAdd($params)
    {
        $firstname = Tools::getValue('firstname');
        $lastname = Tools::getValue('lastname');

        // Obtener el límite de caracteres configurado por el usuario
        $firstnameMaxLength = (int)Configuration::get('MODIFYCUSTOMERFIELDS_FIRSTNAME_LENGTH', 512);
        $lastnameMaxLength = (int)Configuration::get('MODIFYCUSTOMERFIELDS_LASTNAME_LENGTH', 512);

        // Validar que el tamaño no exceda el límite configurado
        if (strlen($firstname) > $firstnameMaxLength || strlen($lastname) > $lastnameMaxLength) {
            throw new PrestaShopException('El nombre y el apellido deben tener un máximo de ' . $firstnameMaxLength . ' y ' . $lastnameMaxLength . ' caracteres respectivamente.');
        }
    }

    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path . 'views/js/back.js');
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
    }

    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }
}

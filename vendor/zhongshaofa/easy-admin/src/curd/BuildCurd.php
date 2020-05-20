<?php


namespace EasyAdmin\curd;

use EasyAdmin\curd\exceptions\TableException;
use EasyAdmin\tool\CommonTool;
use think\exception\FileException;
use think\facade\Db;

/**
 * 快速构建系统CURD
 * Class BuildCurd
 * @package EasyAdmin\curd
 */
class BuildCurd
{

    /**
     * 当前目录
     * @var string
     */
    protected $dir;

    /**
     * 应用目录
     * @var string
     */
    protected $rootDir;

    /**
     * 分隔符
     * @var string
     */
    protected $DS = DS;

    /**
     * 数据库名
     * @var string
     */
    protected $dbName;

    /**
     *  表前缀
     * @var string
     */
    protected $tablePrefix = 'ea';

    /**
     * 主表
     * @var string
     */
    protected $table;

    /**
     * 表注释名
     * @var string
     */
    protected $tableComment;

    /**
     * 主表列信息
     * @var array
     */
    protected $tableColumns;

    /**
     * 数据列表可见字段
     * @var string
     */
    protected $fields;

    /**
     * 是否删除模式
     * @var bool
     */
    protected $delete = false;

    /**
     * 是否强制覆盖
     * @var bool
     */
    protected $force = false;

    /**
     * 关联模型
     * @var array
     */
    protected $relationArray = [];

    /**
     * 控制器对应的URL
     * @var string
     */
    protected $controllerUrl;

    /**
     * 生成的控制器名
     * @var string
     */
    protected $controllerFilename;

    /**
     * 视图名
     * @var string
     */
    protected $viewFilename;

    /**
     * js文件名
     * @var string
     */
    protected $jsFilename;

    /**
     * 生成的模型文件名
     * @var string
     */
    protected $modelFilename;

    /**
     * 复选框字段后缀
     * @var array
     */
    protected $checkboxFieldSuffix = [];

    /**
     * 单选框字段后缀
     * @var array
     */
    protected $radioFieldSuffix = [];

    /**
     * 单图片字段后缀
     * @var array
     */
    protected $imageFieldSuffix = ['image', 'photo', 'icon'];

    /**
     * 多图片字段后缀
     * @var array
     */
    protected $imagesFieldSuffix = ['images', 'photos', 'icons'];

    /**
     * 单文件字段后缀
     * @var array
     */
    protected $fileFieldSuffix = ['file'];

    /**
     * 多文件字段后缀
     * @var array
     */
    protected $filesFieldSuffix = ['files'];

    /**
     * 时间字段后缀
     * @var array
     */
    protected $dateFieldSuffix = ['time', 'date'];

    /**
     * 开关组件字段
     * @var array
     */
    protected $switchFields = ['status'];

    /**
     * 下拉选择字段
     * @var array
     */
    protected $selectFileds;

    /**
     * 富文本字段
     * @var array
     */
    protected $editorFields = [];

    /**
     * 排序字段
     * @var array
     */
    protected $sortFields = [];

    /**
     * 忽略字段
     * @var array
     */
    protected $ignoreFields = ['update_time', 'delete_time'];

    /**
     * 外键字段
     * @var array
     */
    protected $foreignKeyFields = [];

    /**
     * 相关生成文件
     * @var array
     */
    protected $fileList;

    public function __construct()
    {
        $this->tablePrefix = config('database.connections.mysql.prefix');
        $this->dbName = config('database.connections.mysql.database');
        $this->dir = __DIR__;
        $this->rootDir = root_path();
        return $this;
    }

    public function setTable($table)
    {
        $this->table = $table;
        try {

            // 获取表列注释
            $colums = Db::query("SHOW FULL COLUMNS FROM {$this->tablePrefix}{$this->table}");
            foreach ($colums as $vo) {
                $colum = [
                    'type'     => $vo['Type'],
                    'comment'  => !empty($vo['Comment']) ? $vo['Comment'] : $vo['Field'],
                    'required' => $vo['Null'] == "NO" ? true : false,
                    'default'  => $vo['Default'],
                ];
                $this->tableColumns[$vo['Field']] = $colum;
            }

            // 获取表名注释
            $tableSchema = Db::query("SELECT table_name,table_comment FROM information_schema.TABLES WHERE table_schema = 'easyadmin' AND table_name = '{$this->tablePrefix}{$this->table}'");
            $this->tableComment = (isset($tableSchema[0]['table_comment']) && !empty($tableSchema[0]['table_comment'])) ? $tableSchema[0]['table_comment'] : $this->table;
        } catch (\Exception $e) {
            throw new TableException($e->getMessage());
        }

        // 初始化默认控制器名
        $nodeArray = explode('_', $this->table);
        if (count($nodeArray) == 1) {
            $this->controllerFilename = ucfirst($nodeArray[0]);
        } else {
            foreach ($nodeArray as $k => $v) {
                if ($k == 0) {
                    $this->controllerFilename = "{$v}{$this->DS}";
                } else {
                    $this->controllerFilename .= ucfirst($v);
                }
            }
        }

        $this->buildViewJsUrl();

        // 初始化默认模型名
        $this->modelFilename = ucfirst(CommonTool::lineToHump($this->table));

        // 构建数据
        $this->buildStructure();

        return $this;
    }

    public function setRelation($relationTable, $foreignKey, $primaryKey = null, $modelFilename = null)
    {
        if (!isset($this->tableColumns[$foreignKey])) {
            throw new TableException("主表不存在外键字段：{$foreignKey}");
        }
        try {
            $colums = Db::query("SHOW FULL COLUMNS FROM {$this->tablePrefix}{$relationTable}");
            $formatColums = [];
            foreach ($colums as $vo) {
                if (empty($primaryKey) && $vo['Key'] == 'PRI') {
                    $primaryKey = $vo['Field'];
                }
                $colum = [
                    'type'    => $vo['Type'],
                    'comment' => $vo['Comment'],
                    'default' => $vo['Default'],
                ];
                $formatColums[$vo['Field']] = $colum;
            }
            $relation = [
                'modelFilename' => empty($modelFilename) ? ucfirst(CommonTool::lineToHump($relationTable)) : $modelFilename,
                'foreignKey'    => $foreignKey,
                'primaryKey'    => $primaryKey,
                'tableColumns'  => $formatColums,
            ];
            $this->relationArray[$relationTable] = $relation;
            $this->selectFileds[] = $foreignKey;
        } catch (\Exception $e) {
            throw new TableException($e->getMessage());
        }
        return $this;
    }

    public function setControllerFilename($controllerFilename)
    {
        $this->controllerFilename = $controllerFilename;
        $this->buildViewJsUrl();
        return $this;
    }

    public function setFields($fields)
    {
        $this->fields = $fields;
        return $this;
    }

    public function setDelete($delete)
    {
        $this->delete = $delete;
        return $this;
    }

    public function setForce($force)
    {
        $this->force = $force;
        return $this;
    }

    protected function buildViewJsUrl()
    {
        $nodeArray = explode($this->DS, $this->controllerFilename);
        $formatArray = [];
        foreach ($nodeArray as $vo) {
            $formatArray[] = CommonTool::humpToLine(lcfirst($vo));
        }
        $this->controllerUrl = implode('.', $formatArray);
        $this->viewFilename = implode($this->DS, $formatArray);
        $this->jsFilename = $this->viewFilename;
        return $this;
    }

    protected function buildStructure()
    {
        foreach ($this->tableColumns as $key => $val) {

            // 排序
            if (in_array($key, ['sort'])) {
                $this->sortFields[] = $key;
            }

            // 富文本
            if (in_array($key, ['describe', 'content', 'details'])) {
                $this->editorFields[] = $key;
            }

        }
        return $this;
    }

    protected function buildRequiredHtml($require)
    {
        return $require ? 'lay-verify="required"' : "";
    }

    public function render()
    {

        // 初始化数据
        $this->renderData();

        // 控制器
        $this->renderController();

        // 模型
        $this->renderModel();

        // 视图
        $this->renderView();

        // JS
        $this->renderJs();

        return $this;
    }

    protected function renderData()
    {

        // 主表
        foreach ($this->tableColumns as $field => $val) {

            // 过滤字段
            if (in_array($field, $this->ignoreFields)) {
                unset($this->tableColumns[$field]);
                continue;
            }

            // 判断图片
            if ($this->checkContain($field, $this->imageFieldSuffix)) {
                $this->tableColumns[$field]['formType'] = 'image';
                continue;
            }
            if ($this->checkContain($field, $this->imagesFieldSuffix)) {
                $this->tableColumns[$field]['formType'] = 'images';
                continue;
            }

            // 判断文件
            if ($this->checkContain($field, $this->fileFieldSuffix)) {
                $this->tableColumns[$field]['formType'] = 'file';
                continue;
            }
            if ($this->checkContain($field, $this->filesFieldSuffix)) {
                $this->tableColumns[$field]['formType'] = 'files';
                continue;
            }

            // 判断时间
            if ($this->checkContain($field, $this->dateFieldSuffix)) {
                $this->tableColumns[$field]['formType'] = 'date';
                continue;
            }

            // 判断开关
            if (in_array($field, $this->switchFields)) {
                $this->tableColumns[$field]['formType'] = 'switch';
                continue;
            }

            // 判断富文本
            if (in_array($field, $this->editorFields)) {
                $this->tableColumns[$field]['formType'] = 'editor';
                continue;
            }

            // 判断排序
            if (in_array($field, $this->sortFields)) {
                $this->tableColumns[$field]['formType'] = 'sort';
                continue;
            }

            // 判断下拉选择
            if (in_array($field, $this->selectFileds)) {
                $this->tableColumns[$field]['formType'] = 'select';
                continue;
            }

            $this->tableColumns[$field]['formType'] = 'text';
        }

        // 关联表
        foreach ($this->relationArray as $table => $tableVal) {
            foreach ($tableVal['tableColumns'] as $field => $val) {

                // 过滤字段
                if (in_array($field, $this->ignoreFields)) {
                    unset($this->relationArray[$table]['tableColumns'][$field]);
                    continue;
                }

                // 判断图片
                if ($this->checkContain($field, $this->imageFieldSuffix)) {
                    $this->relationArray[$table]['tableColumns'][$field]['formType'] = 'image';
                    continue;
                }
                if ($this->checkContain($field, $this->imagesFieldSuffix)) {
                    $this->relationArray[$table]['tableColumns'][$field]['formType'] = 'images';
                    continue;
                }

                // 判断文件
                if ($this->checkContain($field, $this->fileFieldSuffix)) {
                    $this->relationArray[$table]['tableColumns'][$field]['formType'] = 'file';
                    continue;
                }
                if ($this->checkContain($field, $this->filesFieldSuffix)) {
                    $this->relationArray[$table]['tableColumns'][$field]['formType'] = 'files';
                    continue;
                }

                // 判断时间
                if ($this->checkContain($field, $this->dateFieldSuffix)) {
                    $this->relationArray[$table]['tableColumns'][$field]['formType'] = 'date';
                    continue;
                }

                // 判断开关
                if (in_array($field, $this->switchFields)) {
                    $this->relationArray[$table]['tableColumns'][$field]['formType'] = 'switch';
                    continue;
                }

                // 判断富文本
                if (in_array($field, $this->editorFields)) {
                    $this->relationArray[$table]['tableColumns'][$field]['formType'] = 'editor';
                    continue;
                }

                // 判断排序
                if (in_array($field, $this->sortFields)) {
                    $this->relationArray[$table]['tableColumns'][$field]['formType'] = 'sort';
                    continue;
                }

                // 判断下拉选择
                if (in_array($field, $this->selectFileds)) {
                    $this->relationArray[$table]['tableColumns'][$field]['formType'] = 'select';
                    continue;
                }

                $this->relationArray[$table]['tableColumns'][$field]['formType'] = 'text';
            }
        }

        return $this;

    }

    protected function renderController()
    {
        $controllerFile = "{$this->rootDir}app{$this->DS}admin{$this->DS}controller{$this->DS}{$this->controllerFilename}.php";
        if (empty($this->relationArray)) {
            $controllerIndexMethod = '';
        } else {
            $relationCode = '';
            foreach ($this->relationArray as $key => $val) {
                $relation = CommonTool::lineToHump($key);
                $relationCode = "->withJoin('{$relation}', 'LEFT')\r";
            }
            $controllerIndexMethod = CommonTool::replaceTemplate(
                $this->getTemplate("controller{$this->DS}indexMethod"),
                [
                    'relationIndexMethod' => $relationCode,
                ]);
        }
        $controllerValue = CommonTool::replaceTemplate(
            $this->getTemplate("controller{$this->DS}controller"),
            [
                'controllerNamespace'  => "app\admin\controller\{$this->controllerFilename}",
                'controllerAnnotation' => $this->tableComment,
                'modelFilename'        => "app\admin\model\{$this->modelFilename}",
                'indexMethod'          => $controllerIndexMethod,
            ]);
        $this->fileList[$controllerFile] = $controllerValue;
        return $this;
    }

    protected function renderModel()
    {
        // 主表模型
        $modelFile = "{$this->rootDir}app{$this->DS}admin{$this->DS}model{$this->DS}{$this->modelFilename}.php";
        if (empty($this->relationArray)) {
            $relationList = '';
        } else {
            $relationList = '';
            foreach ($this->relationArray as $key => $val) {
                $relation = CommonTool::lineToHump($key);
                $relationCode = CommonTool::replaceTemplate(
                    $this->getTemplate("model{$this->DS}relation"),
                    [
                        'relationMethod' => $relation,
                        'relationModel'  => "\app\admin\model\\{$val['modelFilename']}",
                        'foreignKey'     => $val['foreignKey'],
                        'primaryKey'     => $val['primaryKey'],
                    ]);
                $relationList .= $relationCode;
            }

        }
        $modelValue = CommonTool::replaceTemplate(
            $this->getTemplate("model{$this->DS}model"),
            [
                'modelNamespace' => "app\admin\model\{$this->modelFilename}",
                'table'          => $this->table,
                'deleteTime'     => isset($this->tableColumns['delete_time']) ? 'delete_time' : false,
                'relationList'   => $relationList,
            ]);
        $this->fileList[$modelFile] = $modelValue;

        // 关联模型
        foreach ($this->relationArray as $key => $val) {
            $relationModelFile = "{$this->rootDir}app{$this->DS}admin{$this->DS}model{$this->DS}{$val['modelFilename']}.php";
            $relationModelValue = CommonTool::replaceTemplate(
                $this->getTemplate("model{$this->DS}model"),
                [
                    'modelNamespace' => "app/admin/model/{$this->modelFilename}",
                    'table'          => $key,
                    'deleteTime'     => isset($val['tableColumns']['delete_time']) ? 'delete_time' : false,
                    'relationList'   => '',
                ]);
            $this->fileList[$relationModelFile] = $relationModelValue;
        }
        return $this;
    }

    protected function renderView()
    {
        // 列表页面
        $viewIndexFile = "{$this->rootDir}app{$this->DS}admin{$this->DS}view{$this->DS}{$this->viewFilename}{$this->DS}index.html";
        $viewIndexValue = CommonTool::replaceTemplate(
            $this->getTemplate("view{$this->DS}index"),
            [
                'controllerUrl' => $this->controllerUrl,
            ]);
        $this->fileList[$viewIndexFile] = $viewIndexValue;

        // 添加页面
        $viewAddFile = "{$this->rootDir}app{$this->DS}admin{$this->DS}view{$this->DS}{$this->viewFilename}{$this->DS}add.html";
        $addFormList = '';
        foreach ($this->tableColumns as $field => $val) {

            if (in_array($field, ['id', 'create_time'])) {
                continue;
            }

            $templateFile = "view{$this->DS}module{$this->DS}input";

            // 根据formType去获取具体模板
            if ($val['formType'] == 'image') {
                $templateFile = "view{$this->DS}module{$this->DS}image";
            } elseif ($val['formType'] == 'images') {
                $templateFile = "view{$this->DS}module{$this->DS}images";
            } elseif ($val['formType'] == 'file') {
                $templateFile = "view{$this->DS}module{$this->DS}file";
            } elseif ($val['formType'] == 'files') {
                $templateFile = "view{$this->DS}module{$this->DS}files";
            } elseif ($val['formType'] == 'editor') {
                $templateFile = "view{$this->DS}module{$this->DS}editor";
            } elseif ($val['formType'] == 'select') {
                $templateFile = "view{$this->DS}module{$this->DS}select";
            } elseif (in_array($field, ['remark'])) {
                $templateFile = "view{$this->DS}module{$this->DS}textarea";
            }

            $addFormList .= CommonTool::replaceTemplate(
                $this->getTemplate($templateFile),
                [
                    'comment'  => $val['comment'],
                    'field'    => $field,
                    'required' => $this->buildRequiredHtml($val['required']),
                    'value'    => $val['default'],
                ]);
        }
        $viewAddValue = CommonTool::replaceTemplate(
            $this->getTemplate("view{$this->DS}form"),
            [
                'formList' => $addFormList,
            ]);
        $this->fileList[$viewAddFile] = $viewAddValue;


        // 编辑页面
        $viewEditFile = "{$this->rootDir}app{$this->DS}admin{$this->DS}view{$this->DS}{$this->viewFilename}{$this->DS}edit.html";
        $editFormList = '';
        foreach ($this->tableColumns as $field => $val) {

            if (in_array($field, ['id', 'create_time'])) {
                continue;
            }

            $templateFile = "view{$this->DS}module{$this->DS}input";

            // 根据formType去获取具体模板
            if ($val['formType'] == 'image') {
                $templateFile = "view{$this->DS}module{$this->DS}image";
            } elseif ($val['formType'] == 'images') {
                $templateFile = "view{$this->DS}module{$this->DS}images";
            } elseif ($val['formType'] == 'file') {
                $templateFile = "view{$this->DS}module{$this->DS}file";
            } elseif ($val['formType'] == 'files') {
                $templateFile = "view{$this->DS}module{$this->DS}files";
            } elseif ($val['formType'] == 'editor') {
                $templateFile = "view{$this->DS}module{$this->DS}editor";
            } elseif ($val['formType'] == 'select') {
                $templateFile = "view{$this->DS}module{$this->DS}select";
            } elseif (in_array($field, ['remark'])) {
                $templateFile = "view{$this->DS}module{$this->DS}textarea";
            }

            $editFormList .= CommonTool::replaceTemplate(
                $this->getTemplate($templateFile),
                [
                    'comment'  => $val['comment'],
                    'field'    => $field,
                    'required' => $this->buildRequiredHtml($val['required']),
                    'value'    => '{$row.' . $field . '|default=\'\'}',
                ]);
        }
        $viewEditValue = CommonTool::replaceTemplate(
            $this->getTemplate("view{$this->DS}form"),
            [
                'formList' => $editFormList,
            ]);
        $this->fileList[$viewEditFile] = $viewEditValue;

        return $this;
    }

    protected function renderJs()
    {
        $jsFile = "{$this->rootDir}public{$this->DS}static{$this->DS}admin{$this->DS}js{$this->DS}{$this->jsFilename}.js";

        $indexCols = "{type: 'checkbox'},\r";

        // 主表字段
        foreach ($this->tableColumns as $field => $val) {
            $indexCols .= $this->formatColsRow("{field: '{$field}', title: '{$val['comment']}'},\r");
        }

        // 关联表
        foreach ($this->relationArray as $table => $tableVal) {
            foreach ($tableVal['tableColumns'] as $field => $val) {
                $indexCols .= $this->formatColsRow("{field: '{$field}', title: '{$val['comment']}'},\r");
            }
        }

        $indexCols .= $this->formatColsRow("{width: 250, title: '操作', templet: ea.table.tool},\r");

        $jsValue = CommonTool::replaceTemplate(
            $this->getTemplate("static{$this->DS}js"),
            [
                'controllerUrl' => $this->controllerUrl,
                'indexCols'     => $indexCols,
            ]);
        $this->fileList[$jsFile] = $jsValue;
        return $this;
    }

    protected function check()
    {
        foreach ($this->fileList as $key => $val) {
            if (is_file($key)) {
                throw new FileException("文件已存在：{$key}");
            }
        }
        return $this;
    }

    public function create()
    {
        $this->check();
        foreach ($this->fileList as $key => $val) {

            // 判断文件夹是否存在,不存在就创建
            $fileArray = explode($this->DS, $key);
            array_pop($fileArray);
            $fileDir = implode($this->DS, $fileArray);
            if (!is_dir($fileDir)) {
                mkdir($fileDir, 0775, true);
            }

            // 写入
            file_put_contents($key, $val);
        }
        return array_keys($this->fileList);
    }

    public function delete()
    {
        $deleteFile = [];
        foreach ($this->fileList as $key => $val) {
            if (is_file($key)) {
                unlink($key);
                $deleteFile[] = $key;
            }
        }
        return $deleteFile;
    }

    protected function checkContain($string, $array)
    {
        foreach ($array as $vo) {
            if (substr($string, 0, strlen($vo)) === $vo) {
                return true;
            }
        }
        return false;
    }

    protected function formatColsRow($value)
    {
        return "                {$value}";
    }

    protected function getTemplate($name)
    {
        return file_get_contents("{$this->dir}{$this->DS}templates{$this->DS}{$name}.code");
    }

}
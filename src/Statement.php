<?php
namespace ClickHouseDB;

/**
 * Class Statement
 * @package ClickHouseDB
 */
class Statement
{
    private $_rawData;
    private $_http_code=-1;
    private $_request=null;
    private $_init=false;

    /**
     * @var Query
     */
    private $query;
    /**
     * @var string
     */
    private $sql=false;
    /**
     * @var array
     */
    private $meta;

    /**
     * @var array
     */
    private $data;

    /**
     * @var array
     */
    private $totals;

    /**
     * @var array
     */
    private $extremes;

    /**
     * @var int
     */
    private $rows;

    /**
     * @var
     */
    private $rows_before_limit_at_least=false;

    /**
     * @var
     */
    private $rawResult;

    /**
     * @var array
     */
    private $array_data=[];

    public function __construct(\Curler\Request $request)
    {
        $this->_request=$request;
        $this->query=$this->_request->getExtendinfo('query');
        $this->sql=$this->_request->getExtendinfo('sql');

    }
    public function sql()
    {
        return $this->sql;
    }
    public function error()
    {
        // @todo normal Exception
        // @todo parse error answer
        // Code: 115, e.displayText() = DB::Exception: Unknown setting readonly[0], e.what() = DB::Exception

        $this->_request->response()->http_code();
        $body=$this->_request->response()->body();
        $this->_request->response()->dump();
        $this->_request->dump();

        throw new \Exception($body);
    }
    public function isError()
    {
        return ($this->_request->response()->http_code()!==200);
    }
    private function init()
    {
        if ($this->_init) return false;

        if (!$this->_request->isResponseExists())
        {
            throw new \Exception('Not have response');
        }

        $this->_http_code=$this->_request->response()->http_code();
        if ($this->_http_code!==200)
        {
            $this->error();
        }
        $this->_rawData=$this->_request->response()->json();


        if (!$this->_rawData)
        {
            $this->_init=true;
            return false;
        }

        foreach (['meta','data','totals','extremes','rows','rows_before_limit_at_least'] as $key)
        {
            if (isset($this->_rawData[$key]))
            {
                $this->{$key}=$this->_rawData[$key];
            }
        }
        if (empty($this->meta))
        {
            throw  new \Exception('Can`t find meta');
        }
        $this->array_data=[];
        foreach ($this->data as $rows)
        {
            $r=[];
            foreach ($this->meta as $meta) {
                $r[$meta['name']]=$rows[$meta['name']];
            }
            $this->array_data[]=$r;
        }


        return true;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function extremes()
    {
        $this->init();
        return $this->extremes;
    }

    /**
     * @return mixed
     */
    public function totalTimeRequest()
    {
        $this->init();
        return $this->_request->response()->total_time();

    }

    /**
     * @return array
     * @throws \Exception
     */
    public function extremesMin()
    {
        $this->init();
        if (empty($this->extremes['min'])) return [];
        return $this->extremes['min'];
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function extremesMax()
    {
        $this->init();
        if (empty($this->extremes['max'])) return [];
        return $this->extremes['max'];
    }
    /**
     * @return mixed
     */
    public function totals()
    {
        $this->init();
        return $this->totals;
    }

    public function dumpRaw()
    {
        print_r($this->_rawData);
    }
    public function dump()
    {
        $this->_request->dump();
        $this->_request->response()->dump();
    }
    public function countAll()
    {
        $this->init();
        return $this->rows_before_limit_at_least;
    }
    public function count()
    {
        $this->init();
        return $this->rows;
    }
    public function fetchOne($key=false)
    {
        $this->init();
        if (isset($this->array_data[0]))
        {
            if ($key)
            {
                if (isset($this->array_data[0][$key]))
                {
                    return $this->array_data[0][$key];
                }
                else
                {
                    return null;
                }
            }
            return $this->array_data[0];
        }
        return null;
    }
    public function rowsAsTree($path)
    {
        $this->init();
        $out=[];
        foreach ($this->array_data as $row)
        {
            $d=$this->array_to_tree($row,$path);
            $out=array_replace_recursive($d,$out);
        }
        return $out;

    }
    public function info_upload()
    {
        $this->init();
        return [
                'size_upload'=>$this->_request->response()->size_upload(),
                'upload_content'=>$this->_request->response()->upload_content_length(),
                'speed_upload'=>$this->_request->response()->speed_upload(),
                'time_request'=>$this->totalTimeRequest()
            ];

    }
    public function rows()
    {
        $this->init();
        return $this->array_data;
    }
    private function array_to_tree($arr, $path=null)
    {
        if (is_array($path))
        {
            $keys=$path;
        }else
        {

            $args=func_get_args();
            array_shift($args);
            if (sizeof($args)<2)
            {

                $separator='.';
                $keys = explode($separator, $path);

            }
            else
            {
                $keys=$args;
            }
        }
        //
        $tree=$arr;
        while (count($keys)) {
            $key=array_pop($keys);
            if (isset($arr[$key])) {$val=$arr[$key];} else $val=$key;

            $tree=array($val => $tree);
        }
        return $tree;
    }
}
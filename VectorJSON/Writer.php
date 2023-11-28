<?php
/**
 * Yordanny Mejías Venegas.
 * Creado: 2022-05-20
 * Modificado: 2022-09-28
 */

namespace VectorJSON;

class Writer{
	private $file;
	private $resource;
	private $key=-1;
	private $current;
	private $depth=512;
	private $flags=0;

	private function __construct($resource){
		$this->resource=$resource;
	}

	public function __destruct(){
		$this->close();
	}

	/**
	 * Ver parámetro de {@see json_encode()}
	 * @return int
	 */
	public function getDepth(){
		return $this->depth;
	}

	/**
	 * Ver parámetro de {@see json_encode()}
	 * @return int
	 */
	public function getFlags(){
		return $this->flags;
	}

	/**
	 * Ver parámetro de {@see json_encode()}
	 * @param int $depth
	 */
	public function setDepth(int $depth){
		$this->depth=$depth;
	}

	/**
	 * Ver parámetro de {@see json_encode()}
	 * @param int $flags
	 */
	public function setFlags(int $flags){
		$this->flags=$flags;
	}

	/**
	 * @param $path
	 * @param bool $append Si es TRUE, empieza a escribir al final del archivo
	 * @return Writer|null
	 */
	public static function open($path, bool $append=false){
		if(!($resource=fopen($path, $append?'a':'w'))) return null;
		$t=new self($resource);
		$t->file=$path;
		return $t;
	}

	/**
	 * ### IMPORTANTE: Este modo de escritura no soporta grandes cantidades de información. Se recomienda usar {@see Writer::open()} y luego comprimir el archivo resultante para evitar fallos
	 * @param $path
	 * @return Writer|null
	 */
	public static function open_gz($path){
		if(!($resource=gzopen($path, 'wb'))) return null;
		$t=new self($resource);
		$t->file=$path;
		return $t;
	}

	/**
	 * ##ADVERTENCIA
	 * Este recurso no se cierra automáticamenta al destruir el objeto, ni llamando a {@see Writer::close()}
	 * @param resource $resource
	 * @return Writer|null
	 */
	public static function open_stream($resource){
		if(!is_resource($resource) || get_resource_type($resource)!='stream' || !stream_get_meta_data($resource)) return null;
		$t=new self($resource);
		return $t;
	}

	public function metadata(){
		return stream_get_meta_data($this->resource);
	}

	public function ready(){
		return $this->resource && get_resource_type($this->resource)=='stream';
	}

	public function close(){
		$closed=($this->file && $this->ready()?fclose($this->resource):false);
		$this->resource=null;
		$this->key=-1;
		$this->current=null;
		return $closed;
	}

	public function convert($data){
		$line=is_null($data)?'':json_encode($data, $this->flags, $this->depth);
		if(is_string($line)) return $line."\n";
		return null;
	}

	public function write($data){
		if(is_string($line=$this->convert($data))){
			return fwrite($this->resource, $line);
		}
		return false;
	}
}


namespace VectorJSON;

class Reader implements \Iterator{
	private $file;
	private $gz;
	private $resource;
	private $alias;
	private $use_main_array=true;
	/**
	 * @var int
	 */
	private $key=-1;
	private $current;
	private $assoc=false;
	private $depth=512;
	private $flags=0;
	private $offset=0;

	private function __construct($resource){
		$this->resource=$resource;
	}

	public function __destruct(){
		$this->close();
	}

	/**
	 * Establece una lista de alias para cambiar los nombres de columnas a la vez que filtra el registro devuelto por {@see Reader::current()} y {@see Reader::readChunk()}
	 * @param array|null $alias Si es null, no se aplican los alias, y todas las columnas originales son devueltas.<br>
	 * Las llaves son los números de columna original, y los valores serán los nuevos nombres en el resultado.
	 * Ejemplo: <code>
	 * array(
	 *     0=>'Nombre',
	 *     1=>'Apellido',
	 *     3=>0,
	 *     4=>4,
	 *     6=>null
	 * )
	 * </code>
	 * En este caso:<ul>
	 * <li>Las columnas 2, 5 y las mayores a 6 se excluyen.</li>
	 * <li>Las columnas 0 y 1 serán renombradas a 'Nombre' y 'Apellido', respectivamente.</li>
	 * <li>La columna 3 pasará a ser la columna 0 del resultado.</li>
	 * <li>La columna 4 y 6 conservarán su nombre/número.</li>
	 * </ul>
	 * El valor resultante será del mismo tipo que el original, es decir, el resultado seguirá siendo objet o array si el original lo era
	 */
	public function alias(?array $alias=null){
		if($alias){
			foreach($alias as $k=>&$v){
				if(is_null($v)) $v=$k;
			}
		}
		$this->alias=$alias;
	}

	public function getAlias(){
		return $this->alias;
	}

	/**
	 * @return bool
	 */
	public function isUseMainArray(){
		return $this->use_main_array;
	}

	/**
	 * @param bool $use_main_array
	 */
	public function setUseMainArray(bool $use_main_array){
		$this->use_main_array=$use_main_array;
	}

	/**
	 * Ver parámetro de {@see json_decode()}
	 * @return bool
	 */
	public function isAssoc(){
		return $this->assoc;
	}

	/**
	 * Ver parámetro de {@see json_decode()}
	 * @return int
	 */
	public function getDepth(){
		return $this->depth;
	}

	/**
	 * Ver parámetro de {@see json_decode()}
	 * @return int
	 */
	public function getFlags(){
		return $this->flags;
	}

	/**
	 * Ver parámetro de {@see json_decode()}
	 * @param bool $assoc
	 */
	public function setAssoc(bool $assoc){
		$this->assoc=$assoc;
	}

	/**
	 * Ver parámetro de {@see json_decode()}
	 * @param int $depth
	 */
	public function setDepth(int $depth){
		$this->depth=$depth;
	}

	/**
	 * Ver parámetro de {@see json_decode()}
	 * @param int $flags
	 */
	public function setFlags(int $flags){
		$this->flags=$flags;
	}

	public function offset(int $offset=0){
		$this->offset=max(0, $offset);
	}

	/**
	 * @return int
	 */
	public function getOffset(){
		return $this->offset;
	}

	public function reopen(){
		if($this->ready()) return false;
		if($this->file){
			if($this->gz){
				if($resource=gzopen($this->file, 'rb')){
					$this->resource=$resource;
					return true;
				}
			}
			else{
				if($resource=fopen($this->file, 'r')){
					$this->resource=$resource;
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param $path
	 * @return Reader|null
	 */
	public static function open($path){
		if(!($resource=fopen($path, 'r'))) return null;
		$t=new self($resource);
		$t->file=$path;
		$t->gz=false;
		return $t;
	}

	/**
	 * @param $path
	 * @return Reader|null
	 */
	public static function open_gz($path){
		if(!($resource=gzopen($path, 'rb'))) return null;
		$t=new self($resource);
		$t->file=$path;
		$t->gz=true;
		return $t;
	}

	/**
	 * ##ADVERTENCIA
	 * Este recurso no se cierra automáticamenta al destruir el objeto, ni llamando a {@see Reader::close()}
	 * @param resource $resource
	 * @return Reader|null
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
		return is_resource($this->resource) && get_resource_type($this->resource)=='stream';
	}

	public function close(){
		$closed=($this->file && $this->ready()?fclose($this->resource):false);
		$this->resource=null;
		$this->key=-1;
		$this->current=null;
		return $closed;
	}

	/**
	 * Obtiene el registro actual, pero no utiliza los alias de columna (ver {@see CSVIterator::alias()})
	 * @return array|null
	 */
	public function current_original(){
		$current=json_decode($this->current, $this->assoc, $this->depth, $this->flags);
		return $current;
	}

	/**
	 * Omite la cantidad de registros indicada
	 * @param int $cant
	 * @return int Cantidad de registros omitidos. Si llega al final, este valor será diferente a la indicada
	 */
	public function skip(int $cant){
		$i=0;
		while($this->valid() && $i<$cant){
			$this->next();
			++$i;
		}
		return $i;
	}

	/**
	 * Obtiene un conjunto de lineas del CSV
	 * @param int $size Tamaño máximo del conjunto
	 * @return array Matriz de datos
	 * @see CSVIterator::alias()
	 */
	public function readChunk(int $size){
		$res=[];
		for($i=0; $i<$size; ++$i){
			if(!$this->valid()) break;
			$res[$this->key()]=$this->current();
			$this->next();
		}
		return $res;
	}

	/**
	 * @return mixed
	 */
	public function current(){
		$current=$this->current_original();
		if($this->use_main_array && is_object($current)) $current=get_object_vars($current);
		if(is_array($this->alias)){
			$current=self::parseAlias($this->alias, $current);
		}
		return $current;
	}

	public function next(){
		++$this->key;
		$this->current=fgets($this->resource);
	}

	/**
	 * @return int
	 */
	public function key(){
		return $this->key;
	}

	public function valid(){
		return is_string($this->current) && strlen($this->current)>0;
	}

	protected function reset(?int $offset){
		if(ftell($this->resource)!==0){
			if(fseek($this->resource, 0)!==0){
				$this->close();
				if(!$this->reopen()) return;
			}
		}
		$this->key=-1;
		$this->current=null;
		if(!is_null($offset)){
			$this->next();
			$this->skip($offset-$this->key);
		}
	}

	protected function _reset($offset){
		if($this->key==$offset) return;
		if($this->key>$offset){
			if(fseek($this->resource, 0)===0){
				$this->key=-1;
			}
			else{
				$this->close();
				return;
			}
		}
		if($this->key<0){
			$this->current=null;
			$this->next();
		}
		$this->skip($offset-$this->key);
	}

	public function rewind(){
		$this->reset($this->offset??0);
	}

	/**
	 * @param array $alias
	 * @param array|object $value
	 * @return array|\stdClass
	 */
	public static function &parseAlias(array &$alias, &$value){
		if(is_array($value)){
			$new=[];
			foreach($alias AS $k=>&$v){
				if(isset($value[$k])) $new[$v]=&$value[$k];
			}
			return $new;
		}
		elseif(is_object($value)){
			$new=new \stdClass();
			foreach($alias AS $k=>&$v){
				if(isset($value[$k])) $new->$v=&$value[$k];
			}
			return $new;
		}
		return $value;
	}
}

# Librería VectorJSON
Esta librería se utiliza para leer y escribir archivos de vectores de datos basados en JSON.

Es similar a los CSV, pero su objetivo principal es ser mucho mas rápido en lectura y escritura.
Además de soportar una mayor variedad de estructuras de datos.

### Escritura:
```PHP
$file='ruta del archivo de escritura';
$writer=\VectorJSON\Writer::open($file);
// Puede guardar cualquier dato procesable por la función json_encode()
$writer->write(['A', 'B', 12.8]);
$writer->write('Texto plano');
$writer->write(50);
$writer->write(['A'=>'Array', 'B'=>'asociativo']);
$writer->close();
```
### Lectura:
```PHP
$file='ruta del archivo de lectura';
$reader=\VectorJSON\Reader::open($file);
foreach($reader AS $index=>$valor){
    // Aquí el uso para cada valor del vector
    // $valor puede ser un dato numérico, string, float, array, ...
}
$reader->close();
```

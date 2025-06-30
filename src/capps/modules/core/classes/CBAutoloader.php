<?php

namespace capps\modules\core\classes;

class CBAutoloader
{
    protected $prefixes = [];
    
    public function register()
    {
        spl_autoload_register([$this, 'loadClass']);
    }
    
    public function addNamespace($prefix, $baseDir, $prepend = false)
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . '/';
        
        if (isset($this->prefixes[$prefix]) === false) {
            $this->prefixes[$prefix] = [];
        }
        
        if ($prepend) {
            array_unshift($this->prefixes[$prefix], $baseDir);
        } else {
            array_push($this->prefixes[$prefix], $baseDir);
        }
    }
    
    public function loadClass($className)
    {
        $prefix = $className;
        
        while (false !== $pos = strrpos($prefix, '\\')) {
            $prefix = substr($className, 0, $pos + 1);
            $relativeClass = substr($className, $pos + 1);
            
            // Versuche exakte Übereinstimmung
            $mappedFile = $this->loadMappedFile($prefix, $relativeClass);
            if ($mappedFile) {
                return $mappedFile;
            }
            
            // NEUE FUNKTION: Versuche case-insensitive
            $mappedFile = $this->loadMappedFileCaseInsensitive($prefix, $relativeClass);
            if ($mappedFile) {
                return $mappedFile;
            }
            
            $prefix = rtrim($prefix, '\\');
        }
        
        return false;
    }
    
    protected function loadMappedFile($prefix, $relativeClass)
    {
        if (isset($this->prefixes[$prefix]) === false) {
            return false;
        }
        
        foreach ($this->prefixes[$prefix] as $baseDir) {
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            
            if ($this->requireFile($file)) {
                return $file;
            }
        }
        
        return false;
    }
    
    /**
     * NEUE FUNKTION: Case-insensitive Suche
     */
    protected function loadMappedFileCaseInsensitive($prefix, $relativeClass)
    {
        // Versuche verschiedene Case-Variationen des Namespace
        $variations = [
            strtolower($prefix),
            strtoupper($prefix),
            ucwords(strtolower($prefix), '\\'),
        ];
        
        foreach ($variations as $prefixVariation) {
            if (isset($this->prefixes[$prefixVariation])) {
                foreach ($this->prefixes[$prefixVariation] as $baseDir) {
                    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
                    
                    if ($this->requireFile($file)) {
                        return $file;
                    }
                }
            }
        }
        
        return false;
    }
    
    protected function requireFile($file)
    {
        if (file_exists($file)) {
            require $file;
            return true;
        }
        
        return false;
    }
    
    /**
     * DEBUG-Funktion: Zeige registrierte Namespaces
     */
    public function getRegisteredNamespaces(): array
    {
        return $this->prefixes;
    }
}
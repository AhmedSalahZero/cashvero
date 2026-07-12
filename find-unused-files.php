<?php
/**
 * Script to find potentially unused PHP and Blade files.
 * Run: php find-unused-files.php
 */

$baseDir = __DIR__;
$viewsDir = $baseDir . '/resources/views';
$appDir = $baseDir . '/app';

// ========== 1. Collect all Blade view names (from file paths) ==========
$bladeFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($viewsDir, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
        $path = $file->getPathname();
        $relative = str_replace($viewsDir . DIRECTORY_SEPARATOR, '', $path);
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        $viewName = str_replace('.blade.php', '', $relative);
        $viewName = str_replace('/', '.', $viewName);
        $bladeFiles[$viewName] = 'resources/views/' . $relative;
    }
}

// ========== 2. Collect all view references from codebase ==========
$viewRefs = [];
$searchDirs = [$baseDir . '/app', $baseDir . '/routes', $baseDir . '/resources/views'];
foreach ($searchDirs as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $ext = $file->getExtension();
        if (!in_array($ext, ['php', 'blade.php'])) continue;
        $content = @file_get_contents($file->getPathname());
        if ($content === false) continue;
        // view('name') or view("name")
        if (preg_match_all("/view\s*\(\s*['\"]([a-zA-Z0-9_.\-]+)['\"]/", $content, $m)) {
            foreach ($m[1] as $name) {
                $viewRefs[$name] = true;
                // also prefix-less like "layouts.dashboard"
                $parts = explode('.', $name);
                for ($i = 1; $i < count($parts); $i++) {
                    $viewRefs[implode('.', array_slice($parts, 0, $i))] = true;
                }
            }
        }
        // @include('name') or @include("name")
        if (preg_match_all("/@include\s*\(\s*['\"]([a-zA-Z0-9_.\-:]+)['\"]/", $content, $m)) {
            foreach ($m[1] as $name) {
                $name = preg_replace('/^.*::/', '', $name); // laravelroles::...
                $viewRefs[$name] = true;
            }
        }
        // @extends('name')
        if (preg_match_all("/@extends\s*\(\s*['\"]([a-zA-Z0-9_.\-:]+)['\"]/", $content, $m)) {
            foreach ($m[1] as $name) {
                $name = preg_replace('/^.*::/', '', $name);
                $viewRefs[$name] = true;
            }
        }
        // view with variable: view($var) - we consider dynamic, skip
    }
}

// Normalize: add partial matches for view names (e.g. "client_view" if we have "client_view.forecast.x")
foreach (array_keys($bladeFiles) as $vn) {
    $p = $vn;
    while (($pos = strrpos($p, '.')) !== false) {
        $p = substr($p, 0, $pos);
        $viewRefs[$p] = true;
    }
}

// ========== 3. Find unused Blade files ==========
$unusedBlade = [];
foreach ($bladeFiles as $viewName => $path) {
    $used = false;
    if (!empty($viewRefs[$viewName])) $used = true;
    foreach (array_keys($viewRefs) as $ref) {
        if (str_starts_with($viewName, $ref . '.') || $viewName === $ref) {
            $used = true;
            break;
        }
    }
    // Also check if any reference contains this view name (e.g. "reports.LetterOfGuaranteeIssuance.$formName")
    foreach (array_keys($viewRefs) as $ref) {
        if (str_contains($ref, $viewName) || str_contains($viewName, $ref)) {
            $used = true;
            break;
        }
    }
    if (!$used) {
        $unusedBlade[$path] = $viewName;
    }
}

// ========== 4. Collect app PHP files (excluding vendor, dompdf, etc.) ==========
$appPhpFiles = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($appDir, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($it as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    if (str_contains($path, 'dompdf') || str_contains($path, 'vendor')) continue;
    $relative = str_replace($baseDir . '/', '', $path);
    $appPhpFiles[] = $relative;
}

// ========== 5. Find PHP files that are never referenced ==========
// Build class name from path: app/Http/Controllers/FooController.php -> App\Http\Controllers\FooController
$referencedPhp = [];
$routesContent = @file_get_contents($baseDir . '/routes/web.php') . @file_get_contents($baseDir . '/routes/api.php');
$routesContent .= implode(' ', array_map(function ($f) use ($baseDir) {
    return @file_get_contents($baseDir . '/' . $f) ?: '';
}, glob($baseDir . '/routes/*.php') ?: []));

foreach ($appPhpFiles as $relPath) {
    $content = @file_get_contents($baseDir . '/' . $relPath) ?: '';
    // Extract class/interface/trait name
    if (preg_match('/^\s*(?:abstract\s+)?(?:class|interface|trait)\s+(\w+)/m', $content, $m)) {
        $className = $m[1];
        $ns = '';
        if (preg_match('/namespace\s+([\w\\\\]+)\s*;/', $content, $nm)) {
            $ns = $nm[1] . '\\';
        }
        $fullClass = $ns . $className;
        $shortClass = $className;
        // Check if referenced: use Full\Class, Full\Class::, new Full\Class, 'Full\Class', etc.
        $pattern = preg_quote($shortClass, '/');
        $patternFull = preg_quote($fullClass, '/');
        $allCode = $routesContent;
        foreach ($appPhpFiles as $r) {
            $allCode .= @file_get_contents($baseDir . '/' . $r) ?: '';
        }
        $allCode .= @file_get_contents($baseDir . '/config/app.php') ?: '';
        if ($relPath === 'app/Http/Kernel.php' || $relPath === 'app/Providers/RouteServiceProvider.php') {
            continue; // always "used" by framework
        }
        // Referenced if: use ...ClassName, ClassName::, new ClassName, '...\ClassName'
        $count = preg_match_all("/\b" . $pattern . "\b/", $allCode);
        $countFull = preg_match_all("/" . str_replace('\\', '\\\\', $patternFull) . "/", $allCode);
        if ($count <= 1 && $countFull <= 1) {
            // Only 1 occurrence = the definition itself
            $referencedPhp[$relPath] = false;
        } else {
            $referencedPhp[$relPath] = true;
        }
    }
}

// Refine: many files are used via config, service provider, or string (e.g. route controller names)
$configDir = $baseDir . '/config';
if (is_dir($configDir)) {
    $configCode = '';
    foreach (glob($configDir . '/*.php') as $cf) {
        $configCode .= @file_get_contents($cf) ?: '';
    }
    foreach ($appPhpFiles as $relPath) {
        $basename = basename($relPath, '.php');
        if (str_contains($configCode, $basename) || str_contains($routesContent, $basename)) {
            $referencedPhp[$relPath] = true;
        }
    }
}

$unusedPhp = [];
foreach ($appPhpFiles as $relPath) {
    if (isset($referencedPhp[$relPath]) && $referencedPhp[$relPath] === false) {
        $unusedPhp[] = $relPath;
    } elseif (!isset($referencedPhp[$relPath])) {
        $unusedPhp[] = $relPath;
    }
}

// ========== Output ==========
echo "=== UNUSED BLADE FILES (not referenced by view(), @include, @extends) ===\n";
echo "Total: " . count($unusedBlade) . "\n\n";
ksort($unusedBlade);
foreach ($unusedBlade as $path => $viewName) {
    echo "  $path  (view: $viewName)\n";
}

echo "\n=== POTENTIALLY UNUSED APP PHP FILES (may have false positives) ===\n";
echo "Total: " . count($unusedPhp) . "\n\n";
sort($unusedPhp);
foreach ($unusedPhp as $p) {
    echo "  $p\n";
}

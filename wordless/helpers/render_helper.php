<?php
error_reporting(E_ALL ^ E_NOTICE);
ini_set('display_errors','On');

class TemplateRenderException extends Exception
{
  public function __construct($title, $message) {
    $this->title = $title;
    parent::__construct($message);
  }

  public function getTitle() {
    return $this->title;
  }

}

  /**
  * Handles rendering of views, templates, partials
  *
  * @ingroup helperclass
  */
class RenderHelper {

  /**
   * Renders a preformatted error display view than dies
   *
   * @param  string $title       A title for the error
   * @param  string $description An explanation about the error
   */
  function render_error($title, $description) {
    ob_end_clean();
    require "templates/error_template.php";
    die();
  }

  /**
   * Renders a template and its contained plartials. Accepts
   * a list of locals variables which will be available inside
   * the code of the template
   *
   * @param  string $name   The template filenames (those not starting
   *                        with an underscore by convention)
   *
   * @param  array  $locals An associative array. Keys will be variables'
   *                        names and values will be variable values inside
   *                        the template
   *
   * @see php.bet/extract
   *
   */
  function render_template($name, $locals = array()) {
    try {
      $valid_filenames = array("$name.html.jade", "$name.html.haml", "$name.haml", "$name.html.php", "$name.php");
      foreach ($valid_filenames as $filename) {
        $path = Wordless::join_paths(Wordless::theme_views_path(), $filename);
        if (is_file($path)) {
          $template_path = $path;
          $format = array_pop(explode('.', $path));
          break;
        }
      }

      if (!isset($template_path)) {
        throw new TemplateRenderException(
          "Template missing",
          "It seems that <code>$name.html.haml</code> or <code>$name.html.php</code> doesn't exist."
        );
      }

      extract($locals);

      switch ($format) {
        case 'haml':
          $compiled_path = compile_haml($template_path);
          include $compiled_path;
          break;
        case 'jade':
          $compiled_path = compile_jade($template_path);
          include $compiled_path;
          break;
        case 'php':
          include $template_path;
          break;
      }
    } catch (TemplateRenderException $e) {
      render_error($e->getTitle(), $e->getMessage());
    } catch (Exception $e) {
      render_error(get_class($e), $e->getMessage());
    }
  }

  function compile_jade($template_path) {
    $cache_path = template_cache_path($template_path);
    if (!compiled_expired($template_path, $cache_path)) {
      return $cache_path;
    }
    ensure_path_writable(dirname($cache_path));

    $jade = new Jade\Jade();
    $view = $jade->render($template_path);
    file_put_contents($cache_path, $view);
    return $cache_path;
  }

  function compile_haml($template_path) {
    $cache_path = template_cache_path($template_path);
    if (!compiled_expired($template_path, $cache_path)) {
      return $cache_path;
    }
    ensure_path_writable(dirname($cache_path));
    $haml = new MtHaml\Environment('php', array('enable_escaper' => false));
    $view = $haml->compileString(file_get_contents($template_path), $template_path);
    file_put_contents($cache_path, $view);
    return $cache_path;
  }

  function compiled_expired($template, $cache) {
    if (!file_exists($cache)) {
      return true;
    }
    $cache_time = filemtime($cache);
    return $cache_time && filemtime($template) >= $cache_time;
  }

  function ensure_path_writable($dir) {
    if (!is_dir($dir)) {
      mkdir($dir, 0760);
    }
    if (!is_writable($dir)) {
      chmod($dir, 0760);
    }
    if (!is_writable($dir)) {
      throw new TemplateRenderException(
        "Directory not writable",
        "It seems that the <code>$dir</code> directory is not writable by the server! Go fix it!"
      );
    }
  }

  function template_cache_path($template_path) {
    $filename = basename($template_path, '.php') . ".php";
    $tmp_dir = Wordless::theme_temp_path();
    return Wordless::join_paths($tmp_dir, $filename);
  }

  /**
  * This is awaiting for documentation
  *
  * @todo
  *   Loss of doc
  */
  function get_partial_content($name, $locals = array()) {
    ob_start();
    render_partial($name, $locals);
    $partial_content = ob_get_contents();
    ob_end_clean();
    return $partial_content;
  }

  /**
   * Renders a partial: those views followed by an underscore
   *   by convention. Partials are inside theme/views.
   *
   * @param  string $name   The template filenames (those not starting
   *                        with an underscore by convention)
   *
   * @param  array  $locals An associative array. Keys will be variables'
   *                        names and values will be variable values inside
   *                        the partial
   */
  function render_partial($name, $locals = array()) {
    $parts = preg_split("/\//", $name);
    if (!preg_match("/^_/", $parts[sizeof($parts)-1])) {
      $parts[sizeof($parts)-1] = "_" . $parts[sizeof($parts)-1];
    }
    render_template(implode($parts, "/"), $locals);
  }

  /**
  * Yield is almost inside every good templates. Based on the
  *   rendering view yield() will insert inside the template the
  *   specific required content (usually called partials)
  *
  * @see render_view()
  * @see render_template()
  */
  function wl_yield() {
    global $current_view, $current_locals;
    render_template($current_view, $current_locals);
  }

  function render_view($name, $options = array()) {
    $options = array_merge(
      array(
        'layout' => 'default',
        'locals' => array()
      ),
      $options
    );
    $layout = $options['layout'];

    ob_start();
    global $current_view, $current_locals;

    $current_view = $name;
    render_template("layouts/$layout", $options['locals']);
    ob_flush();
  }
}

Wordless::register_helper("RenderHelper");

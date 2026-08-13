<?php

namespace Drupal\quicktabs;

use Drupal\Core\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Provides source request handling for Quick Tabs AJAX tab rendering.
 */
trait QuickTabsAjaxSourceRequestTrait {

  /**
   * Gets the source page path for a normal request or Quick Tabs AJAX request.
   */
  protected function getSourcePath(): string {
    $current_path = $this->currentPath->getPath();

    if (!$this->isAjaxRequest()) {
      return $current_path;
    }

    $request = $this->requestStack->getCurrentRequest();
    // Read via all() rather than query->get(): get() throws a
    // BadRequestException on a non-scalar value (e.g. "?quicktabs_path[]=x"),
    // and this runs before the caller's try/finally guard.
    $query = $request ? $request->query->all() : [];
    $source_path = $query[QuickTabsAjax::SOURCE_PATH_QUERY] ?? NULL;
    if (is_string($source_path) && $source_path !== '') {
      return '/' . ltrim($source_path, '/');
    }

    return $current_path;
  }

  /**
   * Creates a request that represents the page that triggered the AJAX tab.
   */
  protected function createSourceRequest(string $source_path): Request {
    $request = $this->requestStack->getCurrentRequest();
    $query = $request ? $request->query->all() : [];
    foreach ($this->getFilteredSourceQueryParameters() as $parameter) {
      unset($query[$parameter]);
    }

    $source_request = Request::create($source_path, 'GET', $query);
    if ($request?->hasSession()) {
      $source_request->setSession($request->getSession());
    }

    try {
      // Resolve the source route with access checks, so a forged
      // quicktabs_path cannot establish route context (e.g. an upcast node)
      // for a page the current user is not allowed to view.
      $source_request->attributes->add($this->router->matchRequest($source_request));
    }
    catch (AccessDeniedHttpException | BadRequestException | MethodNotAllowedException | ResourceNotFoundException) {
      $source_request->attributes->set(RouteObjectInterface::ROUTE_NAME, NULL);
    }

    return $source_request;
  }

  /**
   * Gets query parameters that should not be copied to the source request.
   */
  protected function getFilteredSourceQueryParameters(): array {
    return QuickTabsAjax::filteredQueryParameters();
  }

  /**
   * Determines whether the current request is a Quick Tabs AJAX callback.
   */
  protected function isAjaxRequest(): bool {
    return str_contains($this->currentPath->getPath(), '/quicktabs/ajax/');
  }

}

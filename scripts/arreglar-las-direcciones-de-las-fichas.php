<?php

/**
 * @file
 * Gives the contact cards their addresses back: /customer/33, not /33.
 *
 * The pattern that builds those addresses read the contact type with the token
 * [tec_crm:field_tec_contact_type:0]. That kind of token does not read the field,
 * it renders it, using the card's default display -- and that display has Layout
 * Builder switched on. With Layout Builder on, the fields listed in the display
 * are not what gets rendered; the sections are. So the token comes out empty, and
 * a pattern of "/type/id" collapses to "/id".
 *
 * Nothing complains when this happens. The address is still unique because the id
 * is in it, so the site works and nobody notices until a card is saved and its old
 * address turns into a redirect. That is how this was found: the guardian counts
 * redirects, and a third one had appeared out of nowhere.
 *
 * The fix is to stop rendering and just read the term's name. Same result, no
 * dependency on how anybody has decided to lay the card out.
 */

$config = \Drupal::configFactory()->getEditable('pathauto.pattern.tec_crm_contacts');
$before = (string) $config->get('pattern');
$after = '/[tec_crm:field_tec_contact_type:0:entity:name]/[tec_crm:id]';

echo "Patron:\n";
echo "  antes:  {$before}\n";
echo "  ahora:  {$after}\n";

if ($before !== $after) {
  $config->set('pattern', $after)->save();
  echo "  cambiado.\n";
}
else {
  echo "  ya estaba.\n";
}

// Rebuild the addresses of every card, so the ones already collapsed to "/id"
// come back. Pathauto is told to replace what is there: the old address is wrong,
// keeping it would only mean the fix does nothing until each card is next saved.
\Drupal::service('pathauto.generator')->resetCaches();

echo "\nDirecciones:\n";
$storage = \Drupal::entityTypeManager()->getStorage('tec_crm');
foreach ($storage->loadMultiple() as $contact) {
  $old = \Drupal::service('path_alias.repository')->lookupBySystemPath('/tec_crm/' . $contact->id(), 'en');
  \Drupal::service('pathauto.generator')->updateEntityAlias($contact, 'bulkupdate', ['force' => TRUE]);
  $new = \Drupal::service('path_alias.repository')->lookupBySystemPath('/tec_crm/' . $contact->id(), 'en');
  echo sprintf(
    "  %-4s %-24s %-18s ->  %s\n",
    $contact->id(),
    mb_substr((string) $contact->label(), 0, 24),
    $old['alias'] ?? '(ninguna)',
    $new['alias'] ?? '(ninguna)'
  );
}

// The redirects left behind by the broken addresses. Every time an address
// changes, the redirect module keeps a redirect from the old one so that links
// already out in the world keep working -- which is exactly right in general, and
// pointless here: "/33" was never an address anybody was given, it existed for
// twenty-five minutes between one script and the next. A redirect whose source is
// nothing but a number is one of those, and it is worth clearing out because a
// bare number sitting in the redirect table can shadow a real path later.
$redirect_storage = \Drupal::entityTypeManager()->getStorage('redirect');
$contactos = array_keys(\Drupal::entityTypeManager()->getStorage('tec_crm')->loadMultiple());
$sobras = [];
foreach ($redirect_storage->loadMultiple() as $redirect) {
  $source = trim((string) $redirect->getSourceUrl(), '/');
  // The stored uri, not the built URL: building it turns "/tec_crm/33" back into
  // the alias "/customer/33", so looking for the raw path in it never matches.
  $target = (string) ($redirect->get('redirect_redirect')->uri ?? '');
  if (ctype_digit($source) && in_array((int) $source, $contactos, TRUE) && str_contains($target, '/tec_crm/')) {
    $sobras[] = $redirect;
  }
}
echo "\nRedirecciones que dejo la direccion rota:\n";
if ($sobras) {
  foreach ($sobras as $redirect) {
    echo '  borrando "' . $redirect->getSourceUrl() . '" -> ' . $redirect->getRedirectUrl()->toString() . "\n";
    $redirect->delete();
  }
}
else {
  echo "  ninguna.\n";
}

// The materials pattern reads a field the same rendered way. It is fine today
// because taxonomy terms are not laid out with Layout Builder, but it is the same
// trap, so it gets said out loud rather than left to be discovered.
$materiales = (string) \Drupal::config('pathauto.pattern.tec_inventory_materials')->get('pattern');
echo "\nEl patron de los materiales usa el mismo tipo de token:\n";
echo "  {$materiales}\n";
echo "  funciona porque los terminos no usan Layout Builder. Si algun dia lo usan, se rompe igual.\n";

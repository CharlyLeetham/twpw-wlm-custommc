<?php

public function acl_get_groups ( $levid = NULL ) {

  if ( !levid ) { return; }

  $settings = get_option("twpw_custommc");

  $groupings = array(); // create groupings array
  if( !empty( $settings[$levid]['mcgroup'] ) ) { // if there are groups
    foreach( $settings[$levid]['mcgroup'] as $group ) { // go through each group that's been set
      $groupings[$group] = true;
    }
  }

  return $groupings;

}

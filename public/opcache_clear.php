<?php
if (opcache_reset()) {
    echo 'OPcache cleared successfully.';
} else {
    echo 'OPcache clear failed (may not be enabled).';
}

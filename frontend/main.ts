/**
 * ArsipKu - bundle entry.
 *
 * This is the only module compiled to assets/js/dist/arsipku.js. Booting waits for
 * DOMContentLoaded, so the plain <script src="assets/js/dist/arsipku.js"> tag works
 * wherever the layout puts it.
 */

import { autoBoot } from './ui/bootstrap';

autoBoot();

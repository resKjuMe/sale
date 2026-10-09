import Alpine from 'alpinejs';
import articlePhoto from './article-photo';
import bulkUpload from './bulk-upload';

window.Alpine = Alpine;

Alpine.data('articlePhoto', articlePhoto);
Alpine.data('bulkUpload', bulkUpload);

Alpine.start();

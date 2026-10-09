import Alpine from 'alpinejs';
import aiSuggest from './ai-suggest';
import articlePhoto from './article-photo';
import bulkUpload from './bulk-upload';
import collage from './collage';
import './live-filter';
import sellMode from './sell-mode';

window.Alpine = Alpine;

Alpine.data('aiSuggest', aiSuggest);
Alpine.data('articlePhoto', articlePhoto);
Alpine.data('bulkUpload', bulkUpload);
Alpine.data('collage', collage);
Alpine.data('sellMode', sellMode);

Alpine.start();

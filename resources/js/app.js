import Alpine from 'alpinejs';
import aiBatch from './ai-batch';
import aiSuggest from './ai-suggest';
import articlePhoto from './article-photo';
import bulkUpload from './bulk-upload';
import collage from './collage';
import './live-filter';
import quickFill from './quick-fill';
import sellMode from './sell-mode';

window.Alpine = Alpine;

Alpine.data('aiBatch', aiBatch);
Alpine.data('aiSuggest', aiSuggest);
Alpine.data('articlePhoto', articlePhoto);
Alpine.data('bulkUpload', bulkUpload);
Alpine.data('collage', collage);
Alpine.data('quickFill', quickFill);
Alpine.data('sellMode', sellMode);

Alpine.start();

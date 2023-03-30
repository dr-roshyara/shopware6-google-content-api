import WawisionApiService from '../core/service/wawision.api.service';

const { Application } = Shopware;

Application.addServiceProvider('wawisionService', (container) => {
    const initContainer = Application.getContainer('init');
    return new WawisionApiService(initContainer.httpClient, container.loginService);
});

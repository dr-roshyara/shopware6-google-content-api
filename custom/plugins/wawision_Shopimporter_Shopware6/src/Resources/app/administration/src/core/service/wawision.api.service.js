const ApiService = Shopware.Classes.ApiService;

/**
 * Gateway for the API end point "wawision"
 * @class
 * @extends ApiService
 */
class WawisionApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'wawision') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'wawisionService';
    }

    saveuser(user) {
        const apiRoute = `/_action/${this.getApiBasePath()}/saveuser`;

        return this.httpClient.post(
            apiRoute, {
                user: user
            },
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    buildurl(module, action, id) {
        const apiRoute = `/_action/${this.getApiBasePath()}/buildurl`;

        return this.httpClient.post(
            apiRoute,
            {
                module: module,
                action: action,
                id: id
            },
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    jumpwawision(shopdata) {
        const apiRoute = `/_action/${this.getApiBasePath()}/jumpwawision`;

        return this.httpClient.post(
            apiRoute, {
                shopdata: shopdata
            },
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    getcredentials(token) {
        const apiRoute = `/_action/${this.getApiBasePath()}/getcredentials`;

        return this.httpClient.post(
            apiRoute, {
                token: token
            },
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    usecases() {
        const apiRoute = `/_action/${this.getApiBasePath()}/usecases`;

        return this.httpClient.post(
            apiRoute, { },
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    connectapi(resource, id) {
        const apiRoute = `/_action/${this.getApiBasePath()}/connectapi`;

        return this.httpClient.post(
            apiRoute,
            {
                resource: resource,
                id: id
            },
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

export default WawisionApiService;

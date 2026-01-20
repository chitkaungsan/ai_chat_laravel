import { createStore } from 'vuex';

export default createStore({
    state() {
        return {
            count: 0,
        };
    },
    mutations: {
        increment(state) {
            state.count++;
        },
    },
    actions: {
        incrementAsync({ commit }) {
            setTimeout(() => {
                commit('increment');
            }, 1000);
        },
    },
    getters: {
        doubleCount(state) {
            return state.count * 2;
        },
    },
});

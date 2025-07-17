// src/controllers/stationsController.js

const validateUser = require('../utils/validateUser');
const checkProperty = require('../utils/checkProperty');

// Mock DB functions (to be implemented with real DB or ORM)
const NetworkPerson = {
  findNetworksForPerson: async (personId) => {
    return [
      { networkperson2Network: { Network_ID: 1 } },
      { networkperson2Network: { Network_ID: 2 } }
    ];
  }
};

const Station = {
  getAllStations: async (params) => {
    let conditions = {};

    if (checkProperty(params, 'state_code')) {
      conditions.state = params.state_code;
    }

    if (checkProperty(params, 'person_id')) {
      if (checkProperty(params, 'network_ids')) {
        conditions['$or'] = [
          { observer_id: params.person_id },
          { network_id: { $in: params.network_ids } }
        ];
      } else {
        conditions.observer_id = params.person_id;
      }
    }

    if (checkProperty(params, 'network_ids') && !checkProperty(params, 'person_id')) {
      conditions.network_id = { $in: params.network_ids };
    }

    return [
      { id: 1, name: "Station A", state: "AZ" },
      { id: 2, name: "Station B", state: "CA" }
    ]; // Example static data
  }
};

exports.getStationsForUser = async (req, res) => {
  const params = req.body;

  if (!req.headers.authorization) {
    return res.status(401).json({ stations: null });
  }

  if (checkProperty(params, "access_token") && checkProperty(params, "consumer_key")) {
    const person_id = validateUser.verifyUser(params.access_token, params.consumer_key);
    if (!person_id) {
      return res.status(403).json({ stations: [] });
    }
    params.person_id = person_id;
  }

  if (checkProperty(params, "person_id")) {
    const networkData = await NetworkPerson.findNetworksForPerson(params.person_id);
    const networkIds = networkData.map(n => n.networkperson2Network.Network_ID);
    if (networkIds.length > 0) {
      params.network_ids = networkIds;
    }
    const stations = await Station.getAllStations(params);
    return res.json({ stations });
  } else {
    return res.json({ stations: [] });
  }
};
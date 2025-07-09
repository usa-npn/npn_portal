
// src/controllers/create_stationController.js
exports.getAll = (req, res) => {
  res.json({ message: "GET all create_station" });
};

exports.getOne = (req, res) => {
  res.json({ message: "GET create_station by ID", id: req.params.id });
};

exports.create = (req, res) => {
  res.json({ message: "POST new create_station", body: req.body });
};

exports.update = (req, res) => {
  res.json({ message: "PUT update create_station", id: req.params.id, body: req.body });
};

exports.delete = (req, res) => {
  res.json({ message: "DELETE create_station", id: req.params.id });
};


// src/controllers/stationsController.js
exports.getAll = (req, res) => {
  res.json({ message: "GET all stations" });
};

exports.getOne = (req, res) => {
  res.json({ message: "GET stations by ID", id: req.params.id });
};

exports.create = (req, res) => {
  res.json({ message: "POST new stations", body: req.body });
};

exports.update = (req, res) => {
  res.json({ message: "PUT update stations", id: req.params.id, body: req.body });
};

exports.delete = (req, res) => {
  res.json({ message: "DELETE stations", id: req.params.id });
};

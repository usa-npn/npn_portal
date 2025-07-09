
// src/controllers/wsdlController.js
exports.getAll = (req, res) => {
  res.json({ message: "GET all wsdl" });
};

exports.getOne = (req, res) => {
  res.json({ message: "GET wsdl by ID", id: req.params.id });
};

exports.create = (req, res) => {
  res.json({ message: "POST new wsdl", body: req.body });
};

exports.update = (req, res) => {
  res.json({ message: "PUT update wsdl", id: req.params.id, body: req.body });
};

exports.delete = (req, res) => {
  res.json({ message: "DELETE wsdl", id: req.params.id });
};

// Compile with g++ parse_wav.cpp -o <output file> -lsndfile

#include <iostream>
#include <fstream>
#include <vector>
#include <cstdint>
#include <string>
#include <iomanip>
#include <sndfile.h>
#include <cmath>
#include "json.hpp"

double calculateAverageAmplitude(const std::vector<double>& samples) {
	double sum = 0.0;
	for (double sample : samples) {
		sum += std::abs(sample);
	}
	return sum / samples.size();
}

double calculateTempo(const std::vector<double>& samples, double sampleRate) {
	const int WINDOW_SIZE = 512;
	const double ENERGY_THRESHOLD_RATIO = 3.5;
	const double MIN_TEMPO_BPM = 40.0;
	const double MAX_TEMPO_BPM = 240.0;

	// Loop through the samples and pick out the energies
	std::vector<double> energyEnvelope;
	for (size_t i = 0; i < samples.size(); i += WINDOW_SIZE) {
		double energy = 0.0;
		for (size_t j = i; j < std::min(i + WINDOW_SIZE, samples.size()); ++j) {
			energy += samples[j] * samples[j];
		}
		energyEnvelope.push_back(energy);
	}

	// Loop through the energies and look for samples with more energy than the previous sample
	// Rough indicator of a new beat
	std::vector<double> onsets;
	double prevEnergy = 0.0;
	for (size_t i = 1; i < energyEnvelope.size(); ++i) {
		if (energyEnvelope[i] > ENERGY_THRESHOLD_RATIO * prevEnergy) {
			onsets.push_back(i * WINDOW_SIZE / sampleRate);
		}
		prevEnergy = energyEnvelope[i];
	}

	double totalInterval = 0.0;
	for (size_t i = 1; i < onsets.size(); ++i) {
		totalInterval += onsets[i] - onsets[i - 1];
	}
	double avgInterval = totalInterval / (onsets.size() - 1);
	double tempo = 60.0 / avgInterval;

	return std::max(std::min(tempo, MAX_TEMPO_BPM), MIN_TEMPO_BPM);
}

// As per the canonical .wav header format
struct WavHeader {
	char chunkID[4];
	uint32_t chunkSize;
	char format[4];
	char subchunk1ID[4];
	uint32_t subchunk1Size;
	uint16_t audioFormat;
	uint16_t numChannels;
	uint32_t sampleRate;
	uint32_t byteRate;
	uint16_t blockAlign;
	uint16_t bitsPerSample;
	// No second chunk information here because the second chunk may not be a data chunk
};

int main(int argc, char* argv[]) {
	// Ensuring all parameters are present
	if (argc != 3) {
		std::cerr << "Usage: " << argv[0] << " <input_wav_file> <output_file>" << std::endl;
		return 1;
	}

	// Open provided file and read
	std::ifstream file(argv[1], std::ios::binary);
	
	
	if (!file.is_open()) {
		std::cerr << "Error opening file: " << argv[1] << std::endl;
		return 1;
	}

	// Read the header data
	WavHeader header;
	file.read(reinterpret_cast<char*>(&header), sizeof(WavHeader));

	// Check if the file matches .wav header standards
	if (std::string(header.chunkID, 4) != "RIFF" ||
		std::string(header.format, 4) != "WAVE") {
		std::cerr << "File is not in .wav format." << std::endl;
		file.close();
		return 1;
	}

	// Number of data bytes
	uint32_t dataSize = 0;
	bool dataFound = false;
	while (!dataFound) {
		char subchunkID[4];
		uint32_t subchunkSize;

		// Check that the file hasn't been fully read
		if (file.eof()) {
			std::cerr << "File does not have a data chunk." << std::endl;
			file.close();
			return 1;
		}

		// Read the ID of the next chuck
		file.read(subchunkID, 4);
		// Read the size of the next chunk
		file.read(reinterpret_cast<char*>(&subchunkSize), sizeof(uint32_t));

		// Check that the chunk is the data chunk
		if (std::string(subchunkID, 4) == "data") {
			dataSize = subchunkSize;
			dataFound = true;
		}

		// Skip over the chunk's data
		file.seekg(subchunkSize, std::ios::cur);
	}
	file.close();

	// Compute the number of samples and duration in seconds
	int numSamples = dataSize / ( header.numChannels * header.bitsPerSample / 8.0);
	int duration = numSamples / header.sampleRate;

	// Use the sound library for additional processing
	SNDFILE *inputFile;
	SF_INFO sfInfo;
	sfInfo.format = 0;
	inputFile = sf_open(argv[1], SFM_READ, &sfInfo);
	if (!inputFile) {
		std::cerr << "Error: Couldn't open the input file." << std::endl;
		return 1;
	}

	std::vector<double> samples(numSamples);
	numSamples = sf_read_double(inputFile, samples.data(), numSamples);
	sf_close(inputFile);

	double averageAmplitude = calculateAverageAmplitude(samples);
	double sampleRate = sfInfo.samplerate;
	double tempo = calculateTempo(samples, sampleRate);

	// Put output information into JSON object
	nlohmann::json output = {
		{"channels"     , header.numChannels},
		{"sampleRate"   , header.sampleRate},
		{"bitsPerSample", header.bitsPerSample},
		{"duration"     , duration},
		{"bpm"          , tempo},
		{"amplitude"    , averageAmplitude}
	};

	// Save output to file
	std::ofstream outputFile(argv[2]);
	outputFile << std::setw(4) << output << std::endl;
	outputFile.close();

	std::cout << "Complete." << std::endl;
	return 0;
}
